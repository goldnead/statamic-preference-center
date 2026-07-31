<?php

namespace Goldnead\PreferenceCenter\Sources;

use Goldnead\PreferenceCenter\Data\Access;
use Goldnead\PreferenceCenter\Data\ListRow;
use Goldnead\PreferenceCenter\Support\EmailNormalizer;

/**
 * The mailing-list block, from `goldnead/statamic-marketing`.
 *
 * This source deliberately does not reimplement marketing's rules. It asks
 * `SubscriptionPreferences` for the person's lists and hands writes back to
 * `apply()` and `unsubscribeFromEverything()`, because the two-source read that
 * shipped in marketing 1.8.1 — the contact's own opt-out *and* the suppression
 * table, batched, per row, fail-closed — is the rule this page has to obey, and
 * a second implementation of it is a second thing to get wrong.
 */
class MarketingSource extends Source
{
    public const SERVICE = \Goldnead\Marketing\Services\SubscriptionPreferences::class;

    public const SUBSCRIPTION = \Goldnead\Marketing\Models\Subscription::class;

    public function key(): string
    {
        return 'marketing';
    }

    protected function marker(): string
    {
        return self::SERVICE;
    }

    protected function service(): object
    {
        return app($this->serviceClass());
    }

    protected function serviceClass(): string
    {
        return self::SERVICE;
    }

    public function subscriptionClass(): string
    {
        return self::SUBSCRIPTION;
    }

    /**
     * The marketing view of this person, or null when there is none.
     *
     * A token addresses exactly one subscription and, through its contact, the
     * person's other subscriptions in that one brand. Without a token we find
     * the person the same way marketing does: by normalised address, inside the
     * brand that is already current.
     */
    public function centerFor(Access $access): ?object
    {
        if (! $this->available()) {
            return null;
        }

        $service = $this->service();

        if ($access->marketingToken !== null) {
            return $service->forToken($access->marketingToken);
        }

        $subscription = $this->subscriptionFor($access->email);

        return $subscription ? $service->forSubscription($subscription) : null;
    }

    protected function subscriptionFor(?string $email): mixed
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        $model = $this->subscriptionClass();
        $normalized = EmailNormalizer::normalize($email);

        // Newest first: an unsubscribed row and a live one can both exist for
        // one address, and the live one is the person's current relationship.
        return $model::query()
            ->where('email_normalized', $normalized)
            ->orderByDesc('id')
            ->first();
    }

    /** @return list<ListRow> */
    public function rows(?object $center): array
    {
        if ($center === null) {
            return [];
        }

        return collect($center->rows)->map(fn ($row) => new ListRow(
            handle: $row->handle(),
            name: (string) $row->list->name,
            description: $row->list->description ? (string) $row->list->description : null,
            active: (bool) $row->active,
            blocked: (bool) $row->suppressed,
            current: (bool) $row->current,
        ))->values()->all();
    }

    /**
     * @param  list<string>  $wanted
     * @return array{subscribed:list<string>, unsubscribed:list<string>, refused:list<string>, unknown:list<string>}
     */
    public function apply(object $center, array $wanted): array
    {
        return $this->service()->apply($center, $wanted);
    }

    /** @return list<string> */
    public function unsubscribeFromEverything(object $center): array
    {
        return $this->service()->unsubscribeFromEverything($center);
    }
}
