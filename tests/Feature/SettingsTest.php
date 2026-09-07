<?php

/**
 * Die Einstellungen dieses Addons auf der gemeinsamen Seite.
 *
 * Der Test geht absichtlich den ganzen Weg: über das CP-Formular speichern,
 * und danach **den echten Leser fragen**, nicht `config()`. Ein Test, der nach
 * dem Speichern `config('preference-center.magic_link.enabled')` prüft, belegt
 * nur, dass die Schicht geschrieben hat, was sie geschrieben hat. Er belegt
 * nicht, dass irgendwer das liest — und genau daran scheitert ein Schlüssel,
 * der beim Booten gelesen wird: der Wert steht in der Konfiguration, und die
 * Route, die ihn brauchte, gibt es seit dem Booten.
 *
 * Deshalb wird hier eine öffentliche Adresse aufgerufen, die es vor dem
 * Speichern gab und danach nicht mehr geben darf.
 */

use Goldnead\BrandContext\Settings\SettingsRegistry;
use Goldnead\PreferenceCenter\MagicLink\MagicLinkMail;
use Goldnead\PreferenceCenter\Sources\SuppressionSource;
use Goldnead\PreferenceCenter\Support\Settings;
use Statamic\Facades\Permission;
use Statamic\Facades\User;

beforeEach(function (): void {
    $this->user = User::make()->email('settings@example.com')->makeSuper();
    $this->user->save();
    $this->actingAs($this->user);
});

/**
 * Speichert über das CP-Formular. Das Formular schickt immer den ganzen
 * Abschnitt, also werden die übrigen Felder aus der Konfiguration aufgefüllt;
 * ein Teil-Payload wäre ein 422 und würde nichts über den geänderten Wert
 * aussagen.
 */
function patchPreferenceCenterSettings($test, array $overrides)
{
    $settings = [];

    foreach (array_keys(app(SettingsRegistry::class)->fields('preference-center')) as $key) {
        $settings[$key] = config('preference-center.'.$key);
    }

    return $test->patchJson(cp_route('brand-context.settings.update'), [
        'namespace' => 'preference-center',
        'settings' => array_replace($settings, $overrides),
    ]);
}

it('meldet sich bei der gemeinsamen Einstellungs-Schicht an', function (): void {
    // Das Einzige, was keine andere Behauptung hier auffangen würde: eine
    // Klasse, die den Vertrag erfüllt und sich nie anmeldet, ist eine
    // gemeinsame Seite ohne Abschnitt für dieses Addon.
    $registry = app(SettingsRegistry::class);

    expect($registry->has('preference-center'))->toBeTrue()
        ->and($registry->provider('preference-center'))->toBe(Settings::class)
        ->and($registry->configPath('preference-center'))->toBe('preference-center')
        ->and($registry->permission('preference-center'))->toBe('manage preference-center settings');
});

it('meldet das Recht an, das den Abschnitt bewacht', function (): void {
    // Getrennt von der Behauptung darüber, und nicht überflüssig: die dort
    // geprüfte Zeichenkette kommt aus der Feldliste. Ob Statamic ein Recht
    // dieses Namens überhaupt kennt, steht auf einem anderen Blatt — und wenn
    // nicht, verschwindet der Abschnitt für jeden, der kein Superuser ist,
    // ohne dass irgendwo etwas dazu steht. Dieses Paket hatte bis hierher gar
    // kein Recht, also ist das hier das erste.
    // `boot()`, weil `Permission::extend()` die Rückrufe nur einreiht;
    // ausgeführt werden sie erst hier. Ohne den Aufruf ist die Liste leer und
    // die Behauptung würde für jedes Recht der Welt umfallen, auch für die,
    // die es gibt.
    expect(Permission::boot()->all()->keys())->toContain('manage preference-center settings');
});

it('bietet keinen Schlüssel an, der beim Registrieren der Routen gelesen wird', function (): void {
    $keys = array_keys(app(SettingsRegistry::class)->fields('preference-center'));

    // `routes.*` ist der offensichtliche Fall. `sources.marketing` ist der
    // versteckte: `routes/web.php:63` fragt `MarketingSource::available()`,
    // und das liest diesen Schlüssel — er entscheidet also mit, ob die
    // Token-Route überhaupt entsteht. Ein Schalter dafür wäre halb wirksam.
    expect($keys)->not->toContain('routes.enabled')
        ->and($keys)->not->toContain('routes.prefix')
        ->and($keys)->not->toContain('routes.middleware')
        ->and($keys)->not->toContain('sources.marketing');
});

it('lässt den Abschnitt speichern, ohne dass ein Feld angefasst wurde', function (): void {
    // Der billigste Test hier und der, der am meisten fand. Eine frische
    // Installation öffnet die Seite und drückt Speichern; jedes Feld trägt
    // dann seinen Wert aus der Datei. `sources.*` steht dort auf der
    // Zeichenkette `'auto'`, und als `boolean` deklariert wies die Validierung
    // genau das zurück — der ganze Abschnitt war unspeicherbar, ohne dass
    // irgendein anderer Test es gemerkt hätte.
    patchPreferenceCenterSettings($this, [])->assertRedirect();
});

it('schaltet den Magic Link ab, und der öffentliche Eingang verschwindet', function (): void {
    $eingang = config('preference-center.routes.prefix', '!/preference-center').'/request';

    // Vorher: die Adresse antwortet. Ohne diese Zeile bewiese ein 404 danach
    // nur, dass es die Adresse nie gab.
    $this->get($eingang)->assertOk();

    patchPreferenceCenterSettings($this, ['magic_link.enabled' => false])->assertRedirect();

    // Nachher: derselbe Aufruf, gleicher Prozess, anderer Ausgang. Der
    // Controller hat den gespeicherten Wert gelesen, nicht den aus der Datei.
    $this->get($eingang)->assertNotFound();
});

it('schaltet eine Quelle aus, und der Leser sieht sie nicht mehr', function (): void {
    // Der einzige Feldtyp hier, dessen Bedeutung nicht offensichtlich aus der
    // Konfiguration folgt: der Leser prüft nicht auf „an", sondern auf eine
    // Aus-Liste, und `auto` ist darin nicht enthalten. Ein Wert, den er nicht
    // als „aus" erkennt, würde die Quelle stillschweigend anlassen.
    $quelle = app(SuppressionSource::class);

    expect($quelle->available())->toBeTrue();

    patchPreferenceCenterSettings($this, ['sources.suppression' => 'off'])->assertRedirect();

    expect($quelle->available())->toBeFalse();
});

it('verkürzt die Lebensdauer eines Links und die Mail sagt die neue Zahl', function (): void {
    // Zweiter Leser, anderer Weg: die Zahl steht im Text der Mail, die den
    // Link verschickt, und wird dort zur Renderzeit aus der Konfiguration
    // geholt. Ein Wert, der nur die Signatur beeinflusst, wäre von außen
    // schwer zu unterscheiden von gar keiner Wirkung.
    patchPreferenceCenterSettings($this, ['magic_link.ttl_minutes' => 7])->assertRedirect();

    expect(config('preference-center.magic_link.ttl_minutes'))->toBe(7);

    $mail = new MagicLinkMail([
        ['url' => 'https://example.test/link', 'brand' => null],
    ]);

    expect($mail->build()->buildViewData()['minutes'] ?? null)->toBe(7);
});
