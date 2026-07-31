<?php

namespace Goldnead\PreferenceCenter\Data;

/** One mailing list, as this page shows it. */
final class ListRow
{
    public function __construct(
        public readonly string $handle,
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $active,
        public readonly bool $blocked,
        public readonly bool $current = false,
    ) {}

    public function state(): string
    {
        return $this->blocked ? 'blocked' : ($this->active ? 'active' : 'inactive');
    }
}
