<?php

use Goldnead\PreferenceCenter\Data\TypeRow;

/**
 * Kein Kaestchen fuer einen Weg, den es nicht gibt.
 *
 * Die Matrix zeichnet ihre Spalten aus der globalen Kanalliste. Seit
 * notifications 1.7 kann eine Art aber sagen, welche Kanaele sie unterstuetzt
 * — und ohne diese Frage stand in der Zeile ein anklickbares Kaestchen fuer
 * einen Kanal, den der Versand ohnehin verweigert.
 *
 * Aufgefallen auf der Produktion: `crm.task_assigned` zeigte weiterhin eine
 * Digest-Spalte, obwohl die Art den Kanal seit leadhub 2.5 nicht mehr fuehrt.
 */
function zeile(array $channels): TypeRow
{
    return new TypeRow(
        type: 'crm.task_assigned',
        label: 'Aufgabe zugewiesen',
        required: false,
        channels: $channels,
    );
}

it('kennt die Kanaele, die sie hat', function (): void {
    $row = zeile([
        'in_app' => ['enabled' => true, 'locked' => false, 'reason' => null],
        'mail' => ['enabled' => true, 'locked' => false, 'reason' => null],
    ]);

    expect($row->supports('in_app'))->toBeTrue()
        ->and($row->supports('mail'))->toBeTrue();
});

it('kennt den nicht, den sie nicht hat', function (): void {
    $row = zeile([
        'in_app' => ['enabled' => true, 'locked' => false, 'reason' => null],
        'mail' => ['enabled' => true, 'locked' => false, 'reason' => null],
    ]);

    expect($row->supports('digest'))->toBeFalse();
});

it('unterscheidet "nicht vorhanden" von "aus"', function (): void {
    // Ein ausgeschalteter Kanal ist eine Wahl, ein fehlender ist keine. Wer
    // beides gleich behandelt, zeichnet das Kaestchen wieder.
    $row = zeile([
        'digest' => ['enabled' => false, 'locked' => false, 'reason' => null],
    ]);

    expect($row->supports('digest'))->toBeTrue()
        ->and($row->isEnabled('digest'))->toBeFalse();
});
