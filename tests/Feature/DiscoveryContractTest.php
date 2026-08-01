<?php

use Goldnead\PreferenceCenter\Facades\PreferenceCenter as PreferenceCenterFacade;
use Goldnead\PreferenceCenter\PreferenceCenter;
use Goldnead\PreferenceCenter\Tests\Fixtures\World;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

/**
 * The interface a sibling package discovers this one through.
 *
 * `goldnead/statamic-marketing` no longer serves a preference page of its own.
 * It keeps a one-click unsubscribe path that works with this package absent,
 * and it asks here for every *preference* link it writes into a mail. That makes
 * the two route names and the two methods below a published contract rather than
 * an implementation detail, and this file is what stops them drifting: every
 * assertion here is a promise made to another repository.
 */
beforeEach(function () {
    $this->lists = World::lists();
    World::types();
    $this->token = World::subscriber('jane@example.com', ['newsletter'], $this->lists)->token;
});

it('names the routes a sibling is allowed to hard-code', function () {
    // Renaming any of these is a major release. They are in a sibling's code.
    expect(PreferenceCenter::ROUTE_TOKEN)->toBe('preference-center.token')
        ->and(PreferenceCenter::ROUTE_SHOW)->toBe('preference-center.show')
        ->and(PreferenceCenter::ROUTE_REQUEST)->toBe('preference-center.request');

    expect(Route::has(PreferenceCenter::ROUTE_TOKEN))->toBeTrue()
        ->and(Route::has(PreferenceCenter::ROUTE_SHOW))->toBeTrue()
        ->and(Route::has(PreferenceCenter::ROUTE_REQUEST))->toBeTrue();
});

it('hands a sibling an absolute url for a subscription token', function () {
    $url = app(PreferenceCenter::class)->urlForToken($this->token);

    expect($url)->toStartWith('http')
        ->and($url)->toContain('/t/'.$this->token)
        ->and($url)->toBe(route('preference-center.token', $this->token));
});

it('hands back a url that actually serves the combined page', function () {
    // A resolver's whole job is to prefer this page. A link that resolves to a
    // 404 would be worse than marketing keeping its own — and it would only be
    // discovered in somebody's inbox.
    $url = app(PreferenceCenter::class)->urlForToken($this->token);

    $this->get($url)
        ->assertOk()
        ->assertSee('data-block="lists"', false);
});

it('names the magic-link door for a sender that holds no token', function () {
    expect(app(PreferenceCenter::class)->requestUrl())
        ->toBe(route('preference-center.request'));
});

it('refuses rather than mints a link where marketing is switched off', function () {
    // The route table was built at boot and still carries the token route, so
    // `Route::has()` alone would say yes here. The source has to be asked too,
    // or the caller writes a link into a mail that 500s on the middleware.
    config()->set('preference-center.sources.marketing', false);

    expect(app(PreferenceCenter::class)->urlForToken($this->token))->toBeNull();

    // The magic-link door needs no source at all and stays open.
    expect(app(PreferenceCenter::class)->requestUrl())->not->toBeNull();
});

it('refuses an empty token instead of minting a bare prefix', function () {
    expect(app(PreferenceCenter::class)->urlForToken(''))->toBeNull()
        ->and(app(PreferenceCenter::class)->urlForToken('   '))->toBeNull();
});

it('refuses both doors where the routes were never mounted', function () {
    // `preference-center.routes.enabled=false`, seen from the far side of boot.
    Route::swap(new Router(app('events'), app()));

    expect(app(PreferenceCenter::class)->urlForToken($this->token))->toBeNull()
        ->and(app(PreferenceCenter::class)->requestUrl())->toBeNull();
});

it('is discoverable by the probe the family agreed on, and not by the one that lies', function () {
    // The agreed probe: the class, through the class map.
    expect(class_exists(PreferenceCenter::class))->toBeTrue();

    // The probe that must never be copied. A facade answers through
    // `__callStatic`, so this is false while the method sits on the root object
    // two lines below — the exact reading that took every LeadHub action node
    // down in goldnead/statamic-automations v1.0.3.
    expect(method_exists(PreferenceCenterFacade::class, 'urlForToken'))->toBeFalse();

    expect(PreferenceCenterFacade::getFacadeRoot())->toBeInstanceOf(PreferenceCenter::class)
        ->and(method_exists(PreferenceCenterFacade::getFacadeRoot(), 'urlForToken'))->toBeTrue();

    // And the facade still forwards it, for a host that has no reason to probe.
    expect(PreferenceCenterFacade::urlForToken($this->token))
        ->toBe(route('preference-center.token', $this->token));
});
