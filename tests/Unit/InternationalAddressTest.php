<?php

use Goldnead\PreferenceCenter\Support\EmailNormalizer;

/**
 * A person whose address contains an umlaut has to be able to reach their own
 * preferences.
 *
 * This was broken and nobody could see it. The check was
 * `filter_var($email, FILTER_VALIDATE_EMAIL)`, which predates RFC 6531 and
 * refuses every non-ASCII character — while the rest of the family stores such
 * addresses as contacts, subscribes them to lists, and hands them working token
 * links. The form answers the same neutral sentence to everyone on purpose, so
 * a rejected address is indistinguishable from an unknown one: the failure had
 * no symptom at all.
 *
 * Tested as arithmetic rather than through the page, because that neutrality is
 * exactly what makes an HTTP test unable to tell the two apart.
 */
it('accepts an address a person actually has', function (string $email) {
    expect(EmailNormalizer::looksDeliverable($email))->toBeTrue();
})->with([
    'umlauts in the local part' => 'bärbel.öztürk@beispiel.de',
    'an international domain' => 'post@bäckerei.de',
    'both at once' => 'bärbel@bäckerei.de',
    'the shortest plausible one' => 'a@b.de',
    'a plus address' => 'plus+tag@beispiel.de',
    'a long but legal one' => 'ganz.langer.name@ein-sehr-langer-domainname-den-niemand-tippt.beispiel.de',
    'CJK' => '李明@beispiel.de',
]);

it('still refuses what cannot be delivered', function (string $email) {
    expect(EmailNormalizer::looksDeliverable($email))->toBeFalse();
})->with([
    'no at sign' => 'kein-at',
    'two at signs' => 'zwei@@at.de',
    'nothing before the at' => '@beispiel.de',
    'nothing after it' => 'nutzer@',
    'a space in the local part' => 'mit leer@beispiel.de',
    'a bare hostname' => 'a@b',
    'a header injection attempt' => "nutzer\r\nBcc: fremd@beispiel.de@beispiel.de",
    'angle brackets' => 'nutzer<fremd>@beispiel.de',
]);

it('refuses the empty cases without reaching for a string function', function () {
    expect(EmailNormalizer::looksDeliverable(null))->toBeFalse()
        ->and(EmailNormalizer::looksDeliverable(''))->toBeFalse();
});

it('holds the envelope limits', function () {
    // 64 octets is the largest local part, 254 the largest address. A longer
    // one is not a judgement call, it is undeliverable.
    $zuLang = str_repeat('a', 65).'@beispiel.de';
    $vielZuLang = str_repeat('a', 60).'@'.str_repeat('b', 200).'.de';

    expect(EmailNormalizer::looksDeliverable($zuLang))->toBeFalse()
        ->and(EmailNormalizer::looksDeliverable($vielZuLang))->toBeFalse()
        ->and(EmailNormalizer::looksDeliverable(str_repeat('a', 64).'@beispiel.de'))->toBeTrue();
});
