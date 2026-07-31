<?php

namespace Goldnead\PreferenceCenter\Tests\Fixtures;

use Goldnead\Marketing\Data\MailingList;
use Goldnead\Marketing\Models\MailingListRecord;
use Goldnead\Marketing\Models\Subscription;
use Goldnead\Marketing\Services\SubscriptionService;
use Goldnead\Notifications\Facades\Notifications;

/**
 * A small, readable installation to test against.
 *
 * Everything here is built through the addons' own public API rather than
 * through inserts, so a fixture cannot quietly create a row shape the real code
 * paths would never produce — a preference row written with `DB::table()`, for
 * one, carries an empty `uniqueness_key` and would make the constraint that
 * protects it look absent.
 */
class World
{
    /**
     * The five verteiler of the L13 example, plus the two the cross-brand cases
     * need. Lists are created in the brand that is current when this runs.
     */
    public static function lists(array $handles = ['newsletter', 'chorleitung', 'saenger', 'events', 'angebote']): array
    {
        $lists = [];

        foreach ($handles as $handle) {
            MailingListRecord::query()->firstOrCreate(
                ['handle' => $handle],
                ['name' => ucfirst($handle), 'description' => null, 'double_opt_in' => false],
            );

            $lists[$handle] = new MailingList(handle: $handle, name: ucfirst($handle), doubleOptIn: false);
        }

        return $lists;
    }

    /** A subscribed person on the named lists. Returns the token of the first. */
    public static function subscriber(string $email, array $handles, array $lists): Subscription
    {
        $service = app(SubscriptionService::class);
        $first = null;

        foreach ($handles as $handle) {
            $subscription = $service->subscribe($lists[$handle], $email, [], ['skip_confirmation' => true]);
            $first ??= $subscription;
        }

        return $first->refresh();
    }

    /**
     * The notification types. One of them is `required()`, which is the whole
     * reason this fixture exists as its own method: the registry is per-process
     * and empty until somebody fills it, so a suite that forgets this step
     * would find an empty matrix and call it a pass.
     */
    public static function types(): void
    {
        Notifications::types()->forget();

        Notifications::registerType('account.security', fn ($type) => $type
            ->label('Kontosicherheit')
            ->defaultChannels(['in_app', 'mail'])
            ->required());

        Notifications::registerType('community.mention', fn ($type) => $type
            ->label('Erwähnungen')
            ->defaultChannels(['in_app', 'mail']));

        Notifications::registerType('event.reminder', fn ($type) => $type
            ->label('Event-Erinnerungen')
            ->defaultChannels(['in_app', 'digest']));

        Notifications::registerType('search.alert', fn ($type) => $type
            ->label('Such-Alerts')
            ->defaultChannels(['in_app']));
    }
}
