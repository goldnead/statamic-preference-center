<?php

namespace Goldnead\PreferenceCenter\Data;

/**
 * Everything one page shows, assembled from whichever sources are installed.
 *
 * A block whose source is missing is `null`, not empty: "there are no mailing
 * lists here" and "this installation has no mailing lists at all" are different
 * statements and the page makes different sentences out of them.
 */
final class PreferenceView
{
    /**
     * @param  list<ListRow>|null  $lists
     * @param  list<TypeRow>|null  $types
     * @param  list<string>  $channels
     */
    public function __construct(
        public readonly Access $access,
        public readonly ?array $lists,
        public readonly ?array $types,
        public readonly array $channels,
        public readonly ?string $frequency,
        public readonly SuppressionState $suppression,
    ) {}

    public function hasLists(): bool
    {
        return $this->lists !== null;
    }

    public function hasTypes(): bool
    {
        return $this->types !== null;
    }

    public function hasFrequency(): bool
    {
        return $this->frequency !== null;
    }

    /** The channels the cadence control governs, of those this install has. */
    public function mailChannels(): array
    {
        return array_values(array_intersect($this->channels, \Goldnead\PreferenceCenter\Frequency::MAIL_CHANNELS));
    }
}
