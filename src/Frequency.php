<?php

namespace Goldnead\PreferenceCenter;

/**
 * The four cadences the page offers, and what each of them means downstream.
 *
 * `statamic-notifications` stores only two of these words. Its `frequency`
 * column accepts `daily` and `weekly`; there is no `immediate` and no `never`
 * in that addon, and inventing columns for them would have made this package
 * the owner of a data model it is supposed to be a view over.
 *
 * So the other two are expressed in the storage that already exists, as the
 * channel state they actually describe:
 *
 *     immediate  mail on,  digest off              — it arrives when it happens
 *     daily      mail off, digest on,  daily       — once a day, collected
 *     weekly     mail off, digest on,  weekly      — once a week, collected
 *     never      mail off, digest off              — no notification mail at all
 *
 * Four distinct stored states, so the choice reads back as the choice that was
 * made. `never` deliberately leaves the in-app channel alone: it is a cadence
 * for mail, and a page in the product is not a mailbox.
 *
 * Required types are not touched by any of the four. They are not a cadence
 * question.
 */
final class Frequency
{
    public const IMMEDIATE = 'immediate';

    public const DAILY = 'daily';

    public const WEEKLY = 'weekly';

    public const NEVER = 'never';

    /**
     * Read back only, never settable.
     *
     * The four words describe four uniform states, and the per-type matrix can
     * produce a state that is none of them — some types mailed as they happen,
     * others collected. Defaults alone do that: a type whose default channels
     * include `mail` next to one whose defaults include `digest`.
     *
     * The honest display for that is not the nearest of the four. It is to
     * select none of them and say what is actually the case. Picking `weekly`
     * because a digest exists somewhere would put a caption on the page that
     * its own data contradicts.
     */
    public const MIXED = 'mixed';

    /** The channels this control governs. `in_app` is not one of them. */
    public const MAIL_CHANNELS = ['mail', 'digest'];

    /** @return list<string> */
    public static function all(): array
    {
        return [self::IMMEDIATE, self::DAILY, self::WEEKLY, self::NEVER];
    }

    public static function isKnown(string $value): bool
    {
        return in_array($value, self::all(), true);
    }

    /**
     * The channel state a choice writes.
     *
     * @return array{mail:bool, digest:bool, digest_frequency:?string}
     */
    public static function toChannelState(string $choice): array
    {
        return match ($choice) {
            self::IMMEDIATE => ['mail' => true, 'digest' => false, 'digest_frequency' => null],
            self::DAILY => ['mail' => false, 'digest' => true, 'digest_frequency' => self::DAILY],
            self::WEEKLY => ['mail' => false, 'digest' => true, 'digest_frequency' => self::WEEKLY],
            self::NEVER => ['mail' => false, 'digest' => false, 'digest_frequency' => null],
            default => ['mail' => true, 'digest' => false, 'digest_frequency' => null],
        };
    }

    /**
     * The choice a stored state reads back as.
     *
     * Total by construction: every combination of the two flags maps to one of
     * the four words or to `mixed`, so the control always has something true to
     * display, and every one of the four round-trips exactly.
     */
    public static function fromChannelState(bool $anyMail, bool $anyDigest, string $storedDigestFrequency): string
    {
        if ($anyMail && $anyDigest) {
            return self::MIXED;
        }

        if ($anyDigest) {
            return $storedDigestFrequency === self::DAILY ? self::DAILY : self::WEEKLY;
        }

        return $anyMail ? self::IMMEDIATE : self::NEVER;
    }
}
