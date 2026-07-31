<?php

namespace Goldnead\PreferenceCenter\Tests;

use Goldnead\BrandContext\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Statamic\Providers\StatamicServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../vendor/goldnead/statamic-leadhub/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../vendor/goldnead/statamic-marketing/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../vendor/goldnead/statamic-notifications/database/migrations');

        // Statamic runs bootAddon() inside Statamic::booted callbacks that
        // orchestra/testbench never fires.
        $this->app->getProvider(\Goldnead\Leadhub\ServiceProvider::class)?->bootAddon();
        $this->app->getProvider(\Goldnead\Marketing\ServiceProvider::class)?->bootAddon();
        $this->app->getProvider(\Goldnead\Notifications\ServiceProvider::class)?->bootAddon();
    }

    protected function getPackageProviders($app): array
    {
        return [
            StatamicServiceProvider::class,
            \Goldnead\BrandContext\ServiceProvider::class,
            \Goldnead\IdentityContracts\ServiceProvider::class,
            \Goldnead\Suppression\ServiceProvider::class,
            \Goldnead\Leadhub\ServiceProvider::class,
            \Goldnead\Marketing\ServiceProvider::class,
            \Goldnead\Notifications\ServiceProvider::class,
            \Goldnead\PreferenceCenter\ServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $this->testingConnection());

        $app['config']->set('statamic.users.repository', 'file');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('mail.from', ['address' => 'noreply@example.com', 'name' => 'Test']);

        $app['config']->set('leadhub.storage.driver', 'eloquent');
        $app['config']->set('marketing.storage.driver', 'eloquent');

        // Instant, so nothing in this suite waits on the enumeration floor.
        // The floor itself is measured in MagicLinkEnumerationTest, which sets
        // it back up for exactly that purpose.
        $app['config']->set('preference-center.magic_link.min_response_ms', 0);
    }

    /**
     * In-memory SQLite by default; `DB_DRIVER=mysql` points the identical suite
     * at a real server. SQLite has no InnoDB key limit and no utf8mb4 byte
     * arithmetic, which is exactly how a fully green suite in
     * statamic-notifications let an unbuildable index reach production.
     */
    protected function testingConnection(): array
    {
        if (env('DB_DRIVER', 'sqlite') !== 'mysql') {
            return ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];
        }

        return [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'preference_center_test'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ];
    }

    /**
     * The addon's own routes, plus the stand-ins a sibling addon would mount.
     *
     * `SubstituteBindings` is not decoration: a `Route::bind()` another addon
     * registers applies to every route with that parameter name in every
     * installed package. Without the middleware here, a colliding parameter
     * name passes the whole suite and then 404s on a Hub that has both.
     */
    protected function defineRoutes($router): void
    {
        $router->middleware([
            'web',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ])->group(function ($router) {
            foreach (static::NAMES_A_SIBLING_MIGHT_USE as $name) {
                $router->get('sibling-probe/'.$name.'/{'.$name.'}', fn ($value) => (string) $value);
            }

            // The host's login form, stood in for. `actingAs()` cannot play this
            // part: it installs a user without ever firing `Login`, and the
            // event is the entire subject of `SessionFixationTest`. What matters
            // here is that a real `SessionGuard::login()` runs — including its
            // own session migration, which is why that test follows the cookie
            // the response hands back rather than the one it sent.
            $router->get('pc-test/sign-in', function () {
                \Illuminate\Support\Facades\Auth::login(
                    new \Goldnead\PreferenceCenter\Tests\Fixtures\FixtureUser([
                        'id' => 8100,
                        'email' => 'the-account@example.com',
                        'name' => 'The Account',
                    ])
                );

                return 'signed in';
            });
        });
    }

    /** @var list<string> */
    public const NAMES_A_SIBLING_MIGHT_USE = [
        'token', 'link', 'automation', 'rule', 'template', 'webhook', 'endpoint', 'handle', 'id', 'slug', 'record',
    ];

    protected function enableMultiBrand(): void
    {
        config()->set('brand-context.multi_brand', true);
    }

    protected function makeBrand(string $handle, ?string $name = null): Brand
    {
        return Brand::query()->firstOrCreate(
            ['handle' => $handle],
            ['name' => $name ?? ucfirst($handle)],
        );
    }

    protected function inBrand(string $handle, \Closure $callback): mixed
    {
        $previous = app('brand-context')->hasCurrent() ? app('brand-context')->current() : null;

        app('brand-context')->setCurrent($handle);

        try {
            return $callback();
        } finally {
            $previous ? app('brand-context')->setCurrent($previous) : app('brand-context')->forget();
        }
    }
}
