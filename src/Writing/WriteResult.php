<?php

namespace Goldnead\PreferenceCenter\Writing;

/**
 * What a submission actually did, and what it was not allowed to do.
 *
 * Refusals carry the error-bag key of the control they belong to, so a rejected
 * change appears at the row that was rejected. A reader who cannot see why a
 * change was refused does not open a ticket — they report the mail as spam.
 */
final class WriteResult
{
    /** @var list<array{block:string, target:string, channel:?string, to:mixed}> */
    public array $changes = [];

    /** @var list<array{key:string, reason:string}> */
    public array $refusals = [];

    public function changed(string $block, string $target, mixed $to, ?string $channel = null): void
    {
        $this->changes[] = ['block' => $block, 'target' => $target, 'channel' => $channel, 'to' => $to];
    }

    public function refused(string $key, string $reason): void
    {
        foreach ($this->refusals as $existing) {
            if ($existing['key'] === $key && $existing['reason'] === $reason) {
                return;
            }
        }

        $this->refusals[] = ['key' => $key, 'reason' => $reason];
    }

    public function count(): int
    {
        return count($this->changes);
    }

    public function hasChanges(): bool
    {
        return $this->changes !== [];
    }

    /** @return list<string> */
    public function reasons(): array
    {
        return array_values(array_unique(array_column($this->refusals, 'reason')));
    }
}
