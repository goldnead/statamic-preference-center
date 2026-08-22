<?php

namespace Goldnead\PreferenceCenter\Data;

use Goldnead\PreferenceCenter\Frequency;

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

        /**
         * Die Serien, in denen diese Person steckt — oder `null`, wenn das
         * Automations-Addon fehlt. Siehe SequencesSource::rows() zum
         * Unterschied zwischen `null` und `[]`.
         *
         * @var array<int, object{uuid:string, name:string, opted_out:bool}>|null
         */
        public readonly ?array $sequences = null,
    ) {}

    public function hasLists(): bool
    {
        return $this->lists !== null;
    }

    /**
     * Ein Block ohne Zeilen ist kein Block.
     *
     * Frueher konnte `[]` nur eins heissen: diese Installation hat gar keine
     * Benachrichtigungsarten registriert — und dafuer stand ein erklaerender
     * Satz im Block. Seit die Arten nach Zustaendigkeit gefiltert werden
     * (notifications 1.7), heisst `[]` viel oefter „keine davon gilt fuer
     * dich", und dann ist derselbe Satz schlicht falsch: die Installation
     * kennt sehr wohl welche.
     *
     * Fuer den Menschen davor sind beide Faelle ohnehin dasselbe — es gibt
     * nichts einzustellen. Eine Ueberschrift ohne Inhalt ist schlimmer als
     * keine.
     */
    public function hasTypes(): bool
    {
        return is_array($this->types) && $this->types !== [];
    }

    public function hasSequences(): bool
    {
        return is_array($this->sequences) && $this->sequences !== [];
    }

    public function hasFrequency(): bool
    {
        return $this->frequency !== null;
    }

    /** The channels the cadence control governs, of those this install has. */
    public function mailChannels(): array
    {
        return array_values(array_intersect($this->channels, Frequency::MAIL_CHANNELS));
    }
}
