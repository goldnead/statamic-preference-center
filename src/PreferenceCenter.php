<?php

namespace Goldnead\PreferenceCenter;

use Goldnead\PreferenceCenter\Data\Access;
use Goldnead\PreferenceCenter\Data\PreferenceView;
use Goldnead\PreferenceCenter\Sources\MarketingSource;
use Goldnead\PreferenceCenter\Sources\NotificationsSource;
use Goldnead\PreferenceCenter\Sources\SequencesSource;
use Goldnead\PreferenceCenter\Sources\SuppressionSource;
use Illuminate\Support\Facades\Route;

/**
 * The page, assembled.
 *
 * Read order matters and is not an implementation detail: suppression is
 * resolved first, because its answer changes what the other two blocks are
 * allowed to show. A mailing list the gate has closed is not offered back, and
 * a notification channel that ends in a mailbox is shown off rather than shown
 * as the wish that will never be honoured.
 *
 * ---------------------------------------------------------------------------
 * The discovery contract
 * ---------------------------------------------------------------------------
 *
 * This class is also how a sibling package finds out that the combined page
 * exists and where it lives. `goldnead/statamic-marketing` keeps a minimal
 * one-click unsubscribe path that works with this package absent, and routes
 * every *preference* link through a resolver that prefers this page when it is
 * installed. That resolver is meant to do exactly this:
 *
 *     use Goldnead\PreferenceCenter\PreferenceCenter;
 *
 *     $url = class_exists(PreferenceCenter::class)
 *         ? app(PreferenceCenter::class)->urlForToken($token)
 *         : null;
 *
 *     $url ??= route('marketing.unsubscribe', $token);   // the path that always works
 *
 * Two rules about that probe, both paid for in production:
 *
 * 1. Probe `class_exists()` on **this** class, never `method_exists()` on the
 *    facade. A facade answers its methods through `__callStatic`, so
 *    `method_exists(Facades\PreferenceCenter::class, 'urlForToken')` is false
 *    while the method sits right here — the mistake that took every LeadHub
 *    action node down in `goldnead/statamic-automations` v1.0.3. If a facade
 *    has to be probed at all, probe `Facades\PreferenceCenter::getFacadeRoot()`.
 * 2. Treat `null` as "use your own path". It is returned whenever this package
 *    cannot actually serve the link — routes switched off, marketing absent, an
 *    empty token — and a caller that ignores it publishes a dead link into a
 *    mail nobody can recall.
 *
 * The two route names and the two methods below are a public interface from the
 * release that introduces them, and bound by semver from that tag onwards:
 * renaming either is a major. `tests/Feature/DiscoveryContractTest.php` is what
 * stops them drifting.
 */
class PreferenceCenter
{
    /** The combined page, entered with a marketing subscription token. */
    public const ROUTE_TOKEN = 'preference-center.token';

    /** The same page, entered from a session or a followed magic link. */
    public const ROUTE_SHOW = 'preference-center.show';

    /** The magic-link door, for a sender that holds no token. */
    public const ROUTE_REQUEST = 'preference-center.request';

    public function __construct(
        public readonly MarketingSource $marketing,
        public readonly NotificationsSource $notifications,
        public readonly SuppressionSource $suppression,
        public readonly SequencesSource $sequences,
    ) {}

    public function view(Access $access): PreferenceView
    {
        $suppression = $this->suppression->stateFor($access->email);

        $center = $this->marketing->centerFor($access);
        $lists = $this->marketing->available() ? $this->marketing->rows($center) : null;

        $types = $this->notifications->rows($access, $suppression);

        return new PreferenceView(
            access: $access,
            lists: $lists,
            types: $types,
            channels: $this->notifications->channels(),
            frequency: $this->notifications->frequency($access, $types),
            suppression: $suppression,
            sequences: $this->sequences->rows($access),
        );
    }

    /** The marketing DTO behind the list block, or null. Writes need it. */
    public function marketingCenter(Access $access): ?object
    {
        return $this->marketing->centerFor($access);
    }

    /**
     * The combined page for the person a marketing subscription token names.
     *
     * `null` means this package cannot serve that link here — say so plainly to
     * the caller rather than handing back a URL that 404s once it is in a mail.
     * Three ways that happens, all of them legitimate:
     *
     *  - the token door is not mounted, because marketing is not installed
     *    (`routes/web.php`), or `preference-center.routes.enabled` is off;
     *  - marketing is installed but switched off in
     *    `preference-center.sources.marketing`, which `Route::has()` alone
     *    cannot see because the route table was built at boot;
     *  - the token is empty, which is a caller bug worth failing quietly on
     *    rather than minting `/t/` as a URL.
     *
     * Absolute on purpose. This is written into mail.
     */
    public function urlForToken(string $token): ?string
    {
        if (trim($token) === '' || ! $this->marketing->available() || ! Route::has(self::ROUTE_TOKEN)) {
            return null;
        }

        return route(self::ROUTE_TOKEN, ['pcToken' => $token], true);
    }

    /**
     * The magic-link door: the page a sender points at when it holds no token.
     *
     * `null` when the routes are switched off. Unlike the token door this one
     * needs no source at all — it is the entrance a host with only
     * notifications installed still has.
     */
    public function requestUrl(): ?string
    {
        return Route::has(self::ROUTE_REQUEST)
            ? route(self::ROUTE_REQUEST, [], true)
            : null;
    }
}
