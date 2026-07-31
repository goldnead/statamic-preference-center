<?php

namespace Goldnead\PreferenceCenter\Data;

/**
 * Whether this address may be mailed at all, and whether we could find out.
 *
 * `unavailable` is not a third opinion between blocked and clear — it is the
 * closed answer. When the gate cannot be queried this addon treats the address
 * as blocked, exactly as every send path in this family does, because the
 * alternative is a page that hands consent back on the strength of a database
 * error.
 */
final class SuppressionState
{
    public function __construct(
        public readonly bool $installed,
        public readonly bool $blocked,
        public readonly bool $unavailable = false,
    ) {}

    public static function notInstalled(): self
    {
        return new self(installed: false, blocked: false);
    }

    /** Blocked in the eyes of this page: a real block, or a gate we could not ask. */
    public function blocksMail(): bool
    {
        return $this->blocked || $this->unavailable;
    }
}
