<?php

namespace Goldnead\PreferenceCenter;

use InvalidArgumentException;

/**
 * How a visitor proved they may see and change these settings.
 *
 * The value is not decoration. It is written into every audit record, because
 * a consent record that says only *that* something was changed cannot be
 * defended later; one that says what authorised the change can.
 */
final class Proof
{
    /** The token from a marketing mail. Was in the person's mailbox. */
    public const UNSUBSCRIBE_TOKEN = 'unsubscribe_token';

    /** A signed, expiring link this addon mailed to the address on request. */
    public const MAGIC_LINK = 'magic_link';

    /** An authenticated session. The strongest of the three. */
    public const SESSION = 'session';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::UNSUBSCRIBE_TOKEN, self::MAGIC_LINK, self::SESSION];
    }

    public static function assertKnown(string $proof): string
    {
        if (! in_array($proof, self::all(), true)) {
            throw new InvalidArgumentException("Unknown consent proof [{$proof}].");
        }

        return $proof;
    }
}
