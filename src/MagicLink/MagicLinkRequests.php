<?php

namespace Goldnead\PreferenceCenter\MagicLink;

use Goldnead\PreferenceCenter\Sources\MarketingSource;
use Goldnead\PreferenceCenter\Sources\SuppressionSource;
use Goldnead\PreferenceCenter\Support\EmailNormalizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * "Send me a link."
 *
 * Three properties, all of them security rather than polish:
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
 */
class MagicLinkRequests
{
    public function __construct(
        protected LinkTokenizer $tokenizer,
        protected MarketingSource $marketing,
        protected SuppressionSource $suppression,
    ) {}

    /**
     * @return string one of `sent`, `unknown`, `blocked`, `throttled`, `disabled`
     *                — for logs and tests. It never reaches the visitor.
     */
    public function request(?string $rawEmail, string $origin, int $brandId): string
    {
        if (! config('preference-center.magic_link.enabled', true)) {
            return 'disabled';
        }

        $email = EmailNormalizer::normalize($rawEmail);

        if ($email === null || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'unknown';
        }

        if (! $this->withinLimits($email, $origin, $brandId)) {
            return 'throttled';
        }

        if (! $this->known($email)) {
            return 'unknown';
        }

        if ($this->suppression->stateFor($email)->blocksMail()) {
            Log::channel(config('preference-center.audit.log_channel'))->info(
                'preference-center.magic_link.withheld',
                ['reason' => 'suppressed', 'brand_id' => $brandId],
            );

            return 'blocked';
        }

        Mail::to($email)->send(new MagicLinkMail($this->tokenizer->issue($email, $brandId)));

        return 'sent';
    }

    /**
     * Both limiters are hit for every request that gets this far, including the
     * ones for addresses nobody has ever heard of. Counting only real addresses
     * would turn the limiter itself into the oracle the rest of this class is
     * built to avoid.
     */
    protected function withinLimits(string $email, string $origin, int $brandId): bool
    {
        $limits = (array) config('preference-center.magic_link.throttle', []);

        $keys = [
            'address' => [
                'key' => 'preference-center:magic:address:'.hash('sha256', $brandId.'|'.$email),
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
                ['limiter' => $blocked, 'brand_id' => $brandId],
            );

            return false;
        }

        return true;
    }

    /**
     * Whether this installation has any relationship with the address.
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

        $repository = \Goldnead\Leadhub\Contracts\Repositories\ContactRepository::class;

        if (interface_exists($repository) && app()->bound($repository)) {
            if (app($repository)->findByEmailNormalized($email) !== null) {
                return true;
            }
        }

        return (bool) config('preference-center.magic_link.allow_unknown_addresses', false);
    }
}
