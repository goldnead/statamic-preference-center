<?php

namespace Goldnead\PreferenceCenter;

use Goldnead\BrandContext\Settings\SettingsRegistry;
use Goldnead\PreferenceCenter\Contracts\SenderIdentityResolver;
use Goldnead\PreferenceCenter\Events\PreferencesChanged;
use Goldnead\PreferenceCenter\Listeners\EndTheNoteOnLogin;
use Goldnead\PreferenceCenter\Listeners\RecordPreferenceChange;
use Goldnead\PreferenceCenter\Sending\BrandMailer;
use Goldnead\PreferenceCenter\Sending\BrandSenderIdentity;
use Goldnead\PreferenceCenter\Sources\MarketingSource;
use Goldnead\PreferenceCenter\Sources\NotificationsSource;
use Goldnead\PreferenceCenter\Sources\SequencesSource;
use Goldnead\PreferenceCenter\Sources\SuppressionSource;
use Goldnead\PreferenceCenter\Support\Settings;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Statamic\Facades\Permission;

/**
 * A plain Laravel provider, not a Statamic addon provider.
 *
 * This package has no control panel, no fieldtypes and no Antlers tags; it
 * serves four public URLs to people who have never seen a control panel. There
 * is nothing for `AddonServiceProvider` to register, so extending it would buy
 * a boot order this package does not use.
 *
 * What that decision is *not* is a way to avoid depending on Statamic. It used
 * to be argued that way here, and the argument was false: this package hard
 * requires `goldnead/statamic-brand-context`, which hard requires
 * `statamic/cms`. There has never been an installation of this package without
 * Statamic in it, which is why `statamic/cms` is now in `require` where it was
 * always true, rather than in `suggest` where it read as optional.
 */
class ServiceProvider extends LaravelServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/preference-center.php', 'preference-center');

        // Translations are registered here rather than in boot(): the audit
        // listener and the mailable can both run from a queue worker or a
        // console command, where nothing has booted a view layer yet.
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'preference-center');

        foreach ([MarketingSource::class, NotificationsSource::class, SuppressionSource::class, SequencesSource::class] as $source) {
            $this->app->singleton($source);
        }

        // Who a magic link goes out as, and over which transport. Bound to an
        // interface so a host that keeps sender identities somewhere other
        // than `brands.settings.mail` rebinds it instead of patching the
        // addon; the shipped implementation leaves a single-brand install
        // sending exactly as before.
        $this->app->singleton(SenderIdentityResolver::class, BrandSenderIdentity::class);
        $this->app->singleton(BrandMailer::class);

        $this->app->singleton(PreferenceCenter::class);
        $this->app->alias(PreferenceCenter::class, 'preference-center');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'preference-center');

        Event::listen(PreferencesChanged::class, RecordPreferenceChange::class);

        // Whoever signs in is the person at the keyboard now. See the listener.
        Event::listen(Login::class, EndTheNoteOnLogin::class);

        if (config('preference-center.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        $this->bootSettings();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/preference-center.php' => config_path('preference-center.php'),
            ], 'preference-center-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/preference-center'),
            ], 'preference-center-views');

            $this->publishes([
                __DIR__.'/../resources/lang' => lang_path('vendor/preference-center'),
            ], 'preference-center-translations');
        }
    }

    /**
     * Die Einstellungs-Seite, angemeldet statt gebaut.
     *
     * `statamic-brand-context` bringt Seite, Formular, Validierung, Speicher
     * und Rechteprüfung mit; dieses Paket liefert nur die Feldliste
     * ({@see Settings}) und das Recht, das ihren Abschnitt bewacht. Ein
     * eigener Controller wäre die vierte Kopie derselben Mechanik in dieser
     * Addon-Familie.
     *
     * Aus `boot()`, nicht aus einem `booted()`-Rückruf: die Schicht schreibt
     * die gespeicherten Abweichungen ihrerseits aus `app->booted()` auf die
     * Konfiguration, absichtlich, damit jedes `boot()` sich vorher anmelden
     * konnte.
     *
     * Das Recht wird immer angemeldet, das Feld-Verzeichnis nur, wenn die
     * Schicht da ist. Ein Recht, das nur manchmal existiert, verschwindet aus
     * Rollen, die es tragen.
     */
    protected function bootSettings(): void
    {
        Permission::extend(function (): void {
            Permission::group('preference-center', __('preference-center::public.title'), function (): void {
                Permission::register('manage preference-center settings')
                    ->label(__('preference-center::settings.permission_manage_settings'));
            });
        });

        if (class_exists(SettingsRegistry::class)) {
            $this->app->make(SettingsRegistry::class)->register(Settings::class);
        }
    }
}
