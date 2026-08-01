<?php

namespace Goldnead\PreferenceCenter\MagicLink;

use Goldnead\BrandContext\Models\Brand;
use Goldnead\Leadhub\Contracts\Repositories\ContactRepository;
use Goldnead\PreferenceCenter\Sources\MarketingSource;
use Goldnead\PreferenceCenter\Sources\SuppressionSource;
use Goldnead\PreferenceCenter\Support\EmailNormalizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * "Send me a link."
 *
 * Four properties, all of them security rather than polish:
 *
 * **It does not say whether the address exists.** Every outcome — sent, no such
 * person, blocked, throttled — returns the same page with the same sentence, and
 * the caller holds the response open to a floor so the fast paths cannot be told
 * from the slow one by a stopwatch. An endpoint that answers "unknown address"
 * politely is an address-verification service for whoever asks it hardest.
 *
 * **It is throttled twice.** By address, so one mailbox cannot be flooded; by
 * origin, so the endpoint cannot be pointed at a list of addresses somebody else
 * owns. One limiter without the other is not a limit: per-address alone lets one
 * client mail ten thousand different people, per-origin alone lets ten thousand
 * clients mail one person.
 *
 * **A blocked address is not written to.** The gate exists because a mailbox
 * bounced or its owner complained. "Here is your link to manage preferences" is
 * still mail, and sending it to that mailbox is the behaviour that gets a domain
 * listed.
 *
 * **The address names the brand, not the visitor.** See `brandsToSearch()`.
 */
class MagicLinkRequests
{
    public function __construct(
        protected LinkTokenizer $tokenizer,
        protected MarketingSource $marketing,
        protected SuppressionSource $suppression,
    ) {}

    /**
     * @param  int|null  $brandId  the brand the visitor named, or null for "none
     *                             was named" — which is the ordinary case, because
     *                             the form has no brand field.
     * @return string one of `sent`, `unknown`, `blocked`, `throttled`, `disabled`
     *                — for logs and tests. It never reaches the visitor.
     */
    public function request(?string $rawEmail, string $origin, ?int $brandId = null): string
    {
        if (! config('preference-center.magic_link.enabled', true)) {
            return 'disabled';
        }

        $email = EmailNormalizer::normalize($rawEmail);

        if ($email === null || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'unknown';
        }

        if (! $this->withinLimits($email, $origin)) {
            return 'throttled';
        }

        $found = $this->lookUp($email, $brandId);

        if (! $found['known']) {
            return 'unknown';
        }

        if ($found['links'] === []) {
            return 'blocked';
        }

        Mail::to($email)->send(new MagicLinkMail($found['links']));

        return 'sent';
    }

    /**
     * Both limiters are hit for every request that gets this far, including the
     * ones for addresses nobody has ever heard of. Counting only real addresses
     * would turn the limiter itself into the oracle the rest of this class is
     * built to avoid.
     *
     * The address key is the address and nothing else. It used to carry the
     * brand id as well, which read like tidy namespacing and was a hole: a
     * mailbox is one mailbox however many brands a host runs, and a key of
     * `brand|address` gave every brand its own budget of three — 3×N mails an
     * hour into the same inbox, bounded only by the origin limiter. A limit that
     * protects a key instead of a person protects nobody.
     */
    protected function withinLimits(string $email, string $origin): bool
    {
        $limits = (array) config('preference-center.magic_link.throttle', []);

        $keys = [
            'address' => [
                'key' => 'preference-center:magic:address:'.hash('sha256', $email),
                'max' => (int) ($limits['per_address']['max'] ?? 3),
                'decay' => (int) ($limits['per_address']['decay_minutes'] ?? 60) * 60,
            ],
            'origin' => [
                'key' => 'preference-center:magic:origin:'.hash('sha256', $origin),
                'max' => (int) ($limits['per_origin']['max'] ?? 10),
                'decay' => (int) ($limits['per_origin']['decay_minutes'] ?? 60) * 60,
            ],
        ];

        $blocked = null;

        foreach ($keys as $name => $limit) {
            if (RateLimiter::tooManyAttempts($limit['key'], $limit['max'])) {
                $blocked ??= $name;

                continue;
            }

            RateLimiter::hit($limit['key'], $limit['decay']);
        }

        if ($blocked !== null) {
            Log::channel(config('preference-center.audit.log_channel'))->warning(
                'preference-center.magic_link.throttled',
                ['limiter' => $blocked],
            );

            return false;
        }

        return true;
    }

    /**
     * Which brands know this address, and one link for each of them.
     *
     * `known` is true as soon as one brand has heard of it; `links` is empty
     * when every brand that has is blocked for it. The two are kept apart so
     * that "nobody knows this address" and "we are not allowed to write to it"
     * stay different lines in the log. The visitor gets the same sentence for
     * both, which is the point of the endpoint.
     *
     * @return array{known:bool, links:list<array{url:string, brand:?string}>}
     */
    protected function lookUp(string $email, ?int $brandId): array
    {
        $manager = app('brand-context');
        $known = false;
        $links = [];

        foreach ($this->brandsToSearch($brandId) as $brand) {
            $inBrand = function () use ($email, $brand, $manager, &$known, &$links) {
                if (! $this->known($email)) {
                    return;
                }

                $known = true;

                // Per brand, because the gate is: a hard bounce closes the
                // address everywhere, a complaint only where it was made. A
                // brand that may still write gets its link either way.
                if ($this->suppression->stateFor($email)->blocksMail()) {
                    Log::channel(config('preference-center.audit.log_channel'))->info(
                        'preference-center.magic_link.withheld',
                        ['reason' => 'suppressed', 'brand_id' => (int) $manager->currentId()],
                    );

                    return;
                }

                $links[] = [
                    'url' => $this->tokenizer->issue($email, (int) $manager->currentId()),
                    'brand' => $brand?->name,
                ];
            };

            $brand === null ? $inBrand() : $manager->runFor($brand, $inBrand);
        }

        return ['known' => $known, 'links' => $links];
    }

    /**
     * The brands a request searches — and the reason the form has no brand field.
     *
     * Every other public entrance derives its brand from something the visitor
     * could not have chosen: a token, a signature. This one has nothing to
     * derive from, because an address is not yet known to belong anywhere and
     * that is precisely the question being asked. Three answers were available
     * and two of them are wrong.
     *
     * A **silent default brand** is a bet. On a multi-brand host it searches one
     * audience and tells everybody else the same reassuring sentence, which is
     * then untrue for every person who belongs to one of the other brands.
     * v1.0.0 did something worse than bet: with no brand current at all the
     * scope failed closed, `known()` answered false for *every* address, and the
     * form mailed nothing to anybody on any brand while saying exactly what it
     * says when it works. The silence was the design working as intended —
     * which is how a total outage stayed invisible for a release.
     *
     * A **visible brand field** publishes the brand list to anyone who loads the
     * page, and asks somebody who was mailed by one of several sister sites to
     * remember which company that was.
     *
     * So the address answers it. The lookup runs in every brand, and the mail
     * carries one link per brand that has heard of this address — normally
     * exactly one, and then the mail is the mail it always was. This reveals
     * nothing: the page says the same sentence either way, and the only person
     * who learns which brands know the address is whoever reads that mailbox,
     * who already receives mail from all of them. The per-address limiter counts
     * requests rather than brands, so N brands do not become N times the mail.
     *
     * `pcBrand` still narrows the search and still cannot widen it. It is a hint
     * for a page that belongs to one site, not the mechanism the endpoint
     * depends on.
     *
     * @return iterable<Brand|null> null means "whatever brand is ambient",
     *                              which is what a single-brand install has
     */
    protected function brandsToSearch(?int $brandId): iterable
    {
        $manager = app('brand-context');

        if (! $manager->multiBrandEnabled()) {
            return [null];
        }

        if ($brandId !== null) {
            $named = Brand::query()->find($brandId);

            return $named === null ? [] : [$named];
        }

        return Brand::query()->orderBy('id')->get()->all();
    }

    /**
     * Whether this installation has any relationship with the address, in
     * whichever brand is current while this runs.
     *
     * Without this the endpoint would mail a signed link to anything typed into
     * it, which is an open relay with extra steps. A host that genuinely wants
     * that — a fresh install with neither marketing nor LeadHub, where nobody is
     * known yet — has to say so in config.
     */
    protected function known(string $email): bool
    {
        if ($this->marketing->available()) {
            $model = $this->marketing->subscriptionClass();

            if ($model::query()->where('email_normalized', $email)->exists()) {
                return true;
            }
        }

        $repository = ContactRepository::class;

        if (interface_exists($repository) && app()->bound($repository)) {
            if (app($repository)->findByEmailNormalized($email) !== null) {
                return true;
            }
        }

        return (bool) config('preference-center.magic_link.allow_unknown_addresses', false);
    }
}
