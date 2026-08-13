<?php

/**
 * The German strings use „du", and this is the net under that.
 *
 * The wording was formal until 1.5.1, which made this package the only one of
 * the family that switched the reader's form of address mid-journey: a „du"
 * confirmation mail, a „Sie" preferences link, a page that switched back. It is
 * one search-and-replace away from coming back, and nothing else in this
 * package would notice — the host that reported it has its own assertion, but a
 * host is not where a package's own guarantees belong.
 *
 * Only the address forms, not a snapshot of the sentences. Rewording a sentence
 * has to stay free; switching back to „Sie" does not.
 */
$anreden = [
    // Possessives, and the capital letter is the whole point: „ihre" in the
    // middle of a sentence belongs to somebody, „Ihre" addresses the reader.
    '/(?<![a-zäöüß])Ihr(e|en|em|er|es)?\b/u',

    // „Sie" as an address, not as the pronoun for a plural noun. Those exist in
    // these files on purpose („Redaktionelle E-Mails. Sie beruhen auf deiner
    // Einwilligung") and must survive, so this only catches the imperative
    // shape the formal register uses: a verb first, then „Sie".
    '/\b(Geben|Wählen|Kopieren|Klicken|Öffnen|Prüfen|Tragen|Melden|Ignorieren|Wenden|Beachten|Nutzen|Verwenden)\s+Sie\b/u',

    // And the conditional one: „Wenn Sie …", „Falls Sie …", „Sie sind …".
    '/\b(Wenn|Falls|Sofern|Solange)\s+Sie\b/u',
    '/(?<=[.!?]\s)Sie\s+(sind|haben|können|müssen|werden|bekommen)\b/u',
];

it('addresses the reader informally in every German string', function (string $datei) use ($anreden): void {
    $strings = require __DIR__.'/../../resources/lang/de/'.$datei;

    $flach = [];
    array_walk_recursive($strings, function ($wert, $schluessel) use (&$flach): void {
        if (is_string($wert)) {
            $flach[$schluessel] = $wert;
        }
    });

    expect($flach)->not->toBeEmpty();

    foreach ($flach as $schluessel => $satz) {
        foreach ($anreden as $muster) {
            expect(preg_match($muster, $satz))->toBe(
                0,
                "de/{$datei} → {$schluessel} addresses the reader formally: \"{$satz}\"",
            );
        }
    }
})->with(['mail.php', 'public.php', 'audit.php']);

it('keeps the pronoun that is not an address', function (): void {
    // The guard above must not be so eager that it forbids German. „Sie" for a
    // plural noun is ordinary prose and appears in these files; a test that
    // banned it would be fixed by rewriting perfectly good sentences.
    $strings = require __DIR__.'/../../resources/lang/de/public.php';

    expect($strings['lists_hint'])->toContain('Sie beruhen auf deiner Einwilligung');
});
