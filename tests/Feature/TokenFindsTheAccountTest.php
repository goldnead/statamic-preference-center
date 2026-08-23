<?php

use Goldnead\PreferenceCenter\Identity\AccessResolver;

/**
 * Ein Mensch, eine Einstellung — egal durch welche Tuer er kommt.
 *
 * Ein Token-Besuch wurde bis 1.6.2 immer als Kontakt aufgeloest, auch bei
 * jemandem mit Konto. `notification_preferences` haengt aber an
 * (user_id, contact_uuid): wer ueber den Mail-Link etwas einstellte, schrieb
 * in eine andere Zeile als angemeldet. Derselbe Mensch, zwei
 * Einstellungssaetze, keiner sah den anderen.
 *
 * Aufgefallen ist es erst, als die Arten nach Zustaendigkeit gefiltert wurden:
 * Adrian sah im Nutzertest seine eigenen Community-Einstellungen nicht mehr,
 * obwohl er ein Konto hat.
 *
 * Geprueft wird die Aufloesung selbst und nicht der ganze Weg durch das
 * Marketing-Addon: die Frage ist, ob aus einer Adresse mit Konto eine
 * Konto-Kennung wird, und dafuer reicht eine Anmeldung als schlichtes Objekt.
 */
function loeseAuf(string $email, ?string $contactUuid): object
{
    $resolver = app(AccessResolver::class);

    $methode = new ReflectionMethod($resolver, 'identityFor');
    $methode->setAccessible(true);

    return $methode->invoke($resolver, $email, (object) [
        'contact_uuid' => $contactUuid,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);
}

it('erkennt das Konto hinter einer Adresse', function (): void {
    Statamic\Facades\User::make()->email('jane@example.com')->save();

    expect(loeseAuf('jane@example.com', 'c-1')->userId)->not->toBeNull();
});

it('bleibt beim Kontakt, wenn es kein Konto gibt', function (): void {
    $identity = loeseAuf('niemand@example.com', 'c-2');

    expect($identity->userId)->toBeNull()
        ->and($identity->contactUuid)->toBe('c-2');
});

it('landet auf dem Konto, das zu genau dieser Adresse gehoert', function (): void {
    /*
     * Die Zusage ist nicht "irgendein Konto", sondern "das zu dieser Adresse".
     *
     * Erst wollte ich die contact_uuid der Anmeldung an die Konto-Kennung
     * heften. Das waere falsch gewesen: die angemeldete Sitzung fragt
     * IdentityContext, und wenn der Token etwas anderes anheftet, entstehen
     * wieder zwei Einstellungssaetze — nur an einer anderen Stelle als vorher.
     * Massgeblich ist, was der Host aufloest.
     *
     * Geprueft wird ueber die Adresse und nicht ueber einen Vergleich zweier
     * Kennungen: die Datei-Benutzerablage der Testumgebung gibt bei
     * findByEmail nicht zuverlaessig denselben Datensatz zurueck, den save()
     * eben lieferte. Ein Test, der daran haengt, prueft die Ablage, nicht die
     * Aufloesung.
     */
    Statamic\Facades\User::make()->email('beides@example.com')->save();

    $identity = loeseAuf('beides@example.com', 'c-3');

    expect($identity->userId)->not->toBeNull();

    $konto = Statamic\Facades\User::find($identity->userId);

    expect($konto?->email())->toBe('beides@example.com');
});
