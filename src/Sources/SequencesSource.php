<?php

namespace Goldnead\PreferenceCenter\Sources;

use Goldnead\PreferenceCenter\Data\Access;

/**
 * Die Serien, in denen jemand gerade steckt.
 *
 * Der vierte Block der Seite, und der einzige, der eine Zwischenstufe anbietet:
 * Listen sind An/Aus fuer ein ganzes Thema, die Frequenz gilt fuer alles — eine
 * Serie ist ein einzelner Strang, den man verlassen kann, ohne den Rest
 * aufzugeben. Wer eine fuenfteilige Willkommensstrecke nicht zu Ende lesen
 * will, hatte bis hierher nur die Wahl zwischen ihr und dem Newsletter.
 *
 * Gezeigt werden laufende Serien (ein Lauf wartet noch auf seinen naechsten
 * Schritt) und bereits verlassene. Ohne die verlassenen waere die Seite nach
 * dem Ausstieg leer und der Weg zurueck nirgends zu finden.
 */
class SequencesSource extends Source
{
    public function key(): string
    {
        return 'sequences';
    }

    protected function marker(): string
    {
        return 'Goldnead\\StatamicAutomations\\Services\\SequenceOptOut';
    }

    /**
     * Eine Zeile je Serie, oder `null`, wenn es den Block hier nicht gibt.
     *
     * `null` und nicht `[]`: "kein Block" und "Block ohne Zeilen" sind
     * verschiedene Dinge. Das Leere heisst "du bist in keiner Serie" und ist
     * eine Auskunft, das Fehlen heisst "davon weiss diese Seite nichts".
     *
     * @return array<int, object{uuid:string, name:string, opted_out:bool}>|null
     */
    public function rows(Access $access): ?array
    {
        if (! $this->available() || ! $access->email) {
            return null;
        }

        return $this->service()->sequencesFor($access->email)->all();
    }

    /**
     * Traegt den Wunsch ein: welche Serien sollen bleiben.
     *
     * Die Liste sind die ANGEKREUZTEN, also die gewuenschten — wie bei den
     * Listen daneben. Was fehlt, wird abbestellt. Ein Browser schickt eine
     * nicht angekreuzte Box gar nicht mit, deshalb traegt das Formular den
     * Block-Marker.
     *
     * @param  list<string>  $wanted  UUIDs der Serien, die weiterlaufen sollen
     * @return array{left: list<string>, rejoined: list<string>}
     */
    public function apply(Access $access, array $wanted): array
    {
        $left = [];
        $rejoined = [];

        if (! $this->available() || ! $access->email) {
            return ['left' => $left, 'rejoined' => $rejoined];
        }

        $service = $this->service();
        $email = $access->email;

        foreach ($this->rows($access) ?? [] as $row) {
            $soll = in_array($row->uuid, $wanted, true);

            if ($soll && $row->opted_out) {
                $service->remove($row->uuid, $email);
                $rejoined[] = $row->uuid;

                continue;
            }

            if (! $soll && ! $row->opted_out) {
                $service->add($row->uuid, $email, 'preference_center');
                $left[] = $row->uuid;
            }
        }

        return ['left' => $left, 'rejoined' => $rejoined];
    }

    protected function service(): object
    {
        return app($this->marker());
    }
}
