<?php

namespace Goldnead\PreferenceCenter\Sources;

use Goldnead\Notifications\Preferences\PreferenceResolver;
use Goldnead\Notifications\Types\TypeRegistry;
use Goldnead\PreferenceCenter\Data\Access;
use Goldnead\PreferenceCenter\Data\SuppressionState;
use Goldnead\PreferenceCenter\Data\TypeRow;
use Goldnead\PreferenceCenter\Frequency;

/**
 * The product-notification matrix and the cadence, from
 * `goldnead/statamic-notifications`.
 *
 * Two things are decided here that the notifications addon deliberately does
 * not decide:
 *
 * 1. **What is locked.** `PreferenceResolver::allows()` returns `true` for a
 *    required type on every channel, before it reads anything — correct for a
 *    sender, useless for a form. The lock is computed here and enforced again
 *    in `PreferenceWriter`, because a lock that lives only in the view is a
 *    `disabled` attribute, and `disabled` is a suggestion the browser makes to
 *    itself.
 *
 * 2. **What suppression does to a channel.** A blocked address gets no mail and
 *    no digest, so those two cells are locked off. `in_app` is untouched: the
 *    block is a property of a mailbox, and a page inside the product is not one.
 */
class NotificationsSource extends Source
{
    public const RESOLVER = PreferenceResolver::class;

    public const REGISTRY = TypeRegistry::class;

    /** Why a cell cannot be changed. Rendered as an explanation, not a code. */
    public const LOCK_REQUIRED = 'required';

    public const LOCK_BLOCKED = 'blocked';

    public const LOCK_UNIDENTIFIED = 'unidentified';

    public function key(): string
    {
        return 'notifications';
    }

    protected function marker(): string
    {
        return self::RESOLVER;
    }

    protected function resolver(): object
    {
        return app(self::RESOLVER);
    }

    /** @return list<string> */
    public function channels(): array
    {
        if (! $this->available()) {
            return [];
        }

        return array_values(array_keys((array) config('notifications.channels', [])));
    }

    /**
     * @return list<TypeRow>|null null when the source is not installed.
     */
    public function rows(Access $access, SuppressionState $suppression): ?array
    {
        if (! $this->available()) {
            return null;
        }

        $matrix = $this->resolver()->matrixFor($access->identity);
        $blocked = $suppression->blocksMail();
        $storable = $access->canStoreNotificationPreferences();

        return collect($matrix)->map(function (array $row) use ($blocked, $storable) {
            $channels = [];

            foreach ($row['channels'] as $channel => $cell) {
                $reason = match (true) {
                    (bool) $row['required'] => self::LOCK_REQUIRED,
                    $blocked && in_array($channel, Frequency::MAIL_CHANNELS, true) => self::LOCK_BLOCKED,
                    ! $storable => self::LOCK_UNIDENTIFIED,
                    default => null,
                };

                $channels[$channel] = [
                    // The stored wish, even where a block means it will not be
                    // honoured — the same choice marketing 1.8.1 made for a
                    // blocked list row, which stays checked and goes grey rather
                    // than pretending the person asked for nothing. What the
                    // block does is said once, at the top of the page, instead
                    // of being silently folded into every control.
                    'enabled' => (bool) $cell['enabled'],
                    'locked' => $reason !== null,
                    'reason' => $reason,
                ];
            }

            return new TypeRow(
                type: (string) $row['type'],
                label: (string) $row['label'],
                required: (bool) $row['required'],
                channels: $channels,
            );
        })->values()->all();
    }

    /**
     * The cadence, read back out of the channel state it was written as.
     *
     * @param  list<TypeRow>|null  $rows
     */
    public function frequency(Access $access, ?array $rows): ?string
    {
        if (! $this->available() || $rows === null) {
            return null;
        }

        $optional = array_filter($rows, fn (TypeRow $row) => ! $row->required);

        if ($optional === []) {
            return null;
        }

        $anyMail = false;
        $anyDigest = false;

        foreach ($optional as $row) {
            $anyMail = $anyMail || $row->isEnabled('mail');
            $anyDigest = $anyDigest || $row->isEnabled('digest');
        }

        return Frequency::fromChannelState(
            $anyMail,
            $anyDigest,
            (string) $this->resolver()->digestFrequency($access->identity),
        );
    }

    /**
     * Write one cell.
     *
     * Never called for a locked cell — `PreferenceWriter` decides that — and it
     * refuses an unidentified visitor here as well, because this is the call
     * that would otherwise write a row keyed on two NULLs and share it with
     * every other unplaced visitor on the installation.
     */
    public function set(Access $access, string $type, string $channel, bool $enabled, ?string $frequency = null): void
    {
        if (! $access->canStoreNotificationPreferences()) {
            throw new \LogicException('Refusing to store a notification preference for an unidentified visitor.');
        }

        $this->resolver()->set($access->identity, $type, $channel, $enabled, $frequency);
    }
}
