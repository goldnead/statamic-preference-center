<?php

namespace Goldnead\PreferenceCenter\Support;

use Goldnead\BrandContext\Contracts\ProvidesSettings;
use Goldnead\BrandContext\Settings\SettingsRegistry;

/**
 * Die Einstellungen, die ein Betreiber im Control Panel ändern darf, und die
 * einzige Stelle, die weiß, welche das sind.
 *
 * **Nur die Feldliste steht hier.** Seite, Formular, Validierung, Speicher,
 * Rechteprüfung und die Markendimension kommen aus der gemeinsamen
 * Einstellungs-Schicht in `statamic-brand-context`; angemeldet wird diese
 * Klasse über {@see SettingsRegistry} im Service Provider. Kein eigener
 * Controller, keine eigene Vue-Seite, keine eigene Route, keine eigene
 * Tabelle.
 *
 * Dieses Paket hat selbst kein Control Panel — vier öffentliche Adressen, mehr
 * nicht. Genau deshalb war bis hierher jeder seiner Werte für den Betreiber
 * unerreichbar: die Lebensdauer eines Links, die Drosselung, die
 * Absenderadresse, alles nur in `.env`. Die gemeinsame Seite gibt ihm diese
 * Werte, ohne dass dieses Paket einen eigenen Bildschirm bekommt.
 *
 * **Was nicht hier steht, und warum.**
 *
 * - `routes.enabled`, `routes.prefix`, `routes.middleware`. Alle drei werden
 *   gelesen, während die Routen entstehen (`ServiceProvider::boot()`,
 *   `routes/web.php:26,27,64,65`). Die Einstellungs-Schicht schreibt ihre
 *   Werte aus einem `app->booted()`-Rückruf, also danach: ein Feld hier wäre
 *   erst nach dem nächsten Deploy wirksam.
 * - `sources.marketing`. **Dieselbe Falle, eine Ebene versteckt.**
 *   `routes/web.php:63` fragt `MarketingSource::available()`, und das liest
 *   `sources.marketing`. Der Schalter entscheidet also mit, ob die
 *   Token-Route `/t/{pcToken}` überhaupt existiert. Auf der Seite angeboten
 *   würde er den Marketing-Block sofort verschwinden lassen und die Route
 *   trotzdem bis zum nächsten Deploy stehen lassen — halb wirksam ist
 *   schlechter als gar nicht. Die drei anderen Quellen werden ausschließlich
 *   zur Anfragezeit gefragt und sind hier.
 * - `delivery.mail_headers` und `delivery.ignored_query_parameters`. Das erste
 *   ist eine verschachtelte Abbildung von Kopfzeilenname auf Wert, das zweite
 *   Teil des Protokolls zwischen Mailanbieter und Signaturprüfung. Beide
 *   bleiben in `config/preference-center.php`, wo die Tabelle der Anbieter
 *   danebensteht, die sie erklärt.
 * - `audit.log_channel`. Ein Laravel-Logkanal. Welche Kanäle es gibt, entscheidet
 *   `config/logging.php` des Hosts; ein Freitextfeld darüber ist ein Tippfehler
 *   entfernt von einer Prüfspur, die ins Leere schreibt.
 */
class Settings implements ProvidesSettings
{
    /**
     * Steht auf jeder Zeile in `brand_settings.namespace`. Ihn später zu
     * ändern verwaist jede Abweichung, die eine Installation gespeichert hat.
     */
    public static function settingsNamespace(): string
    {
        return 'preference-center';
    }

    public static function settingsConfigPath(): string
    {
        return 'preference-center';
    }

    /**
     * Das Recht, das den Abschnitt dieses Addons bewacht.
     *
     * Neu, also frei wählbar; nach der Regel `manage <handle> settings` mit
     * dem Paketnamen ohne `statamic-`-Präfix. Der Bindestrich bleibt darin
     * stehen: der Name ist ab jetzt in Rollen eingetragen, und ihn später
     * glattzuziehen wäre ein stiller Rechteentzug.
     */
    public static function settingsPermission(): string
    {
        return 'manage preference-center settings';
    }

    /**
     * @return array<int, array{title: string, description: string, fields: array<int, array<string, mixed>>}>
     */
    public static function settingsGroups(): array
    {
        return [
            [
                'title' => __('preference-center::settings.groups.magic_link.title'),
                'description' => __('preference-center::settings.groups.magic_link.description'),
                'fields' => [
                    static::field('magic_link.enabled', 'boolean'),
                    static::field('magic_link.ttl_minutes', 'integer', ['min' => 1]),
                    static::field('magic_link.session_minutes', 'integer', ['min' => 1]),
                    // Boden bei 0, nicht bei 1: „keine Mindestlaufzeit" ist ein
                    // echter, wenn auch unkluger Zustand, und ein erfundenes
                    // Minimum von 1 ms wäre genauso wirkungslos wie 0.
                    static::field('magic_link.min_response_ms', 'integer', ['min' => 0]),
                    static::field('magic_link.allow_unknown_addresses', 'boolean'),
                ],
            ],
            [
                'title' => __('preference-center::settings.groups.throttle.title'),
                'description' => __('preference-center::settings.groups.throttle.description'),
                'fields' => [
                    static::field('magic_link.throttle.per_address.max', 'integer', ['min' => 1]),
                    static::field('magic_link.throttle.per_address.decay_minutes', 'integer', ['min' => 1]),
                    static::field('magic_link.throttle.per_origin.max', 'integer', ['min' => 1]),
                    static::field('magic_link.throttle.per_origin.decay_minutes', 'integer', ['min' => 1]),
                ],
            ],
            [
                'title' => __('preference-center::settings.groups.sender.title'),
                'description' => __('preference-center::settings.groups.sender.description'),
                'fields' => [
                    static::field('magic_link.from.address', 'string', ['nullable' => true]),
                    static::field('magic_link.from.name', 'string', ['nullable' => true]),
                ],
            ],
            [
                'title' => __('preference-center::settings.groups.sources.title'),
                'description' => __('preference-center::settings.groups.sources.description'),
                'fields' => [
                    static::field('sources.notifications', 'select', ['options' => static::sourceOptions()]),
                    static::field('sources.suppression', 'select', ['options' => static::sourceOptions()]),
                    static::field('sources.sequences', 'select', ['options' => static::sourceOptions()]),
                ],
            ],
            [
                'title' => __('preference-center::settings.groups.audit.title'),
                'description' => __('preference-center::settings.groups.audit.description'),
                'fields' => [
                    static::field('audit.leadhub', 'boolean'),
                ],
            ],
        ];
    }

    /**
     * Die zwei Antworten, die ein Quellen-Schalter hat.
     *
     * **Zwei, nicht drei.** Die Konfigurationsdatei kennt `auto`, `true` und
     * `false`, aber `Sources\Source::available()` fragt nur, ob der Wert in
     * seiner Aus-Liste steht, und prüft danach immer noch, ob die Klassen des
     * Pakets da sind. `auto` und `true` verhalten sich damit identisch. Eine
     * dritte Option „an" wäre eine Wahl ohne Unterschied — und ausgerechnet
     * eine, die verspricht, den Block auch ohne das Paket zu zeigen.
     *
     * **Und `select`, nicht `boolean`.** Der Vorgabewert der Datei ist die
     * Zeichenkette `'auto'`. Als `boolean` deklariert lehnt die Validierung
     * genau diesen Wert ab: auf einer frischen Installation wäre der ganze
     * Abschnitt unspeicherbar, bis jemand alle drei Felder anfasst. Gemessen
     * am 07.09.2026, bevor dieser Kommentar hier stand.
     *
     * Nicht abgefangen ist der Fall, dass jemand die Umgebungsvariable auf ein
     * echtes `true` oder `false` gesetzt hat: dann steht in der Konfiguration
     * ein Wahrheitswert, den keine der beiden Optionen trifft, und das
     * Formular verlangt eine ausdrückliche Wahl, bevor der Abschnitt gespeichert
     * werden kann. Das ist sichtbar und einmalig — die Alternative wäre, den
     * rohen Wert als dritte Option danebenzulegen, und ein `false`, das als
     * leere Zeichenkette zurückkäme, ist im Leser nicht mehr „aus".
     *
     * @return array<string, string>
     */
    protected static function sourceOptions(): array
    {
        return [
            'auto' => __('preference-center::settings.source_options.auto'),
            'off' => __('preference-center::settings.source_options.off'),
        ];
    }

    /**
     * Ein Feld, mit Beschriftung und Hilfetext aus den Sprachdateien.
     *
     * Der Übersetzungsschlüssel ist der Konfigurationspfad mit flachgelegten
     * Punkten: ein Punkt in einem Sprachschlüssel ist für den Übersetzer ein
     * Pfadtrenner, und `settings.fields.magic_link.ttl_minutes.label` würde als
     * vier verschachtelte Felder gesucht, die es nicht gibt.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected static function field(string $key, string $type, array $extra = []): array
    {
        $handle = str_replace('.', '_', $key);

        return array_merge([
            'key' => $key,
            'type' => $type,
            'label' => __("preference-center::settings.fields.{$handle}.label"),
            'description' => __("preference-center::settings.fields.{$handle}.description"),
            'nullable' => false,
        ], $extra);
    }
}
