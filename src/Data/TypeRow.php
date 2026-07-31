<?php

namespace Goldnead\PreferenceCenter\Data;

/**
 * One notification type across every channel.
 *
 * `locked` is why this class exists rather than passing the notifications
 * matrix straight through. `PreferenceResolver::allows()` answers `true` for a
 * required type on every channel and never looks at storage — which is right
 * for a sender and useless for a form, because a cell that is on and must stay
 * on looks exactly like a cell that is on and may be turned off.
 */
final class TypeRow
{
    /**
     * @param  array<string, array{enabled:bool, locked:bool, reason:?string}>  $channels
     */
    public function __construct(
        public readonly string $type,
        public readonly string $label,
        public readonly bool $required,
        public readonly array $channels,
    ) {}

    public function isLocked(string $channel): bool
    {
        return (bool) ($this->channels[$channel]['locked'] ?? true);
    }

    public function isEnabled(string $channel): bool
    {
        return (bool) ($this->channels[$channel]['enabled'] ?? false);
    }

    public function reason(string $channel): ?string
    {
        return $this->channels[$channel]['reason'] ?? null;
    }
}
