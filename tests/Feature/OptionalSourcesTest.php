<?php

use Goldnead\Marketing\Models\Subscription;
use Goldnead\Notifications\Models\NotificationPreference;
use Goldnead\PreferenceCenter\Tests\Fixtures\FixtureUser;
use Goldnead\PreferenceCenter\Tests\Fixtures\World;

/**
 * All three sources are optional, and none of them is optional in the weak
 * sense of "the page does not crash".
 *
 * A host with only notifications has to get a working page, and so does a host
 * with only marketing — this is not decoration: in the Hub the centre is set up
 * for FamilyStack, where the mix is not the mix on adriangoldner.com.
 *
 * Absence is exercised through the config switch, because a suite that has all
 * three installed cannot uninstall one. The `class_exists` branch underneath it
 * is exercised separately, in `tests/Unit/SourceDetectionTest.php`, by pointing
 * a subclass at a class that genuinely does not exist.
 */
beforeEach(function () {
    $this->lists = World::lists();
    World::types();
    $this->token = World::subscriber('jane@example.com', ['newsletter'], $this->lists)->token;
});

it('serves a page with the lists gone', function () {
    config()->set('preference-center.sources.marketing', false);

    $user = new FixtureUser(['email' => 'jane@example.com']);
    $user->setAttribute('id', 4711);

    $response = $this->actingAs($user)->get(route('preference-center.show'))->assertOk();

    expect($response->getContent())
        ->not->toContain('data-block="lists"')
        ->toContain('data-block="types"')
        ->toContain('data-block="frequency"');

    // And the write path refuses the block as firmly as the render path omits
    // it: a form posted from a page rendered before the switch was thrown does
    // not sneak past.
    $this->actingAs($user)->followingRedirects()
        ->post(route('preference-center.update'), [
            'action' => 'save', 'blocks' => ['lists'], 'lists' => ['angebote'],
        ])
        ->assertOk()
        ->assertSee(__('preference-center::public.refused_source_absent'));

    expect(Subscription::query()->where('list_handle', 'angebote')->exists())->toBeFalse();
});

it('serves a page with the notifications gone', function () {
    config()->set('preference-center.sources.notifications', false);

    $response = $this->get(route('preference-center.token', $this->token))->assertOk();

    expect($response->getContent())
        ->toContain('data-block="lists"')
        ->not->toContain('data-block="types"')
        ->not->toContain('data-block="frequency"');

    $this->followingRedirects()
        ->post(route('preference-center.token.update', $this->token), [
            'action' => 'save', 'blocks' => ['types', 'frequency'],
            'types' => ['community.mention' => ['mail' => '1']],
            'frequency' => 'daily',
        ])
        ->assertOk()
        ->assertSee(__('preference-center::public.refused_source_absent'));

    expect(NotificationPreference::query()->count())->toBe(0);
});

it('serves a page with the suppression gone, and says nothing is blocked', function () {
    config()->set('preference-center.sources.suppression', false);

    $response = $this->get(route('preference-center.token', $this->token))->assertOk();

    // Marketing keeps its own gate call regardless, so its rows can still be
    // blocked. What this addon stops claiming is the page-level verdict — and
    // that is the honest reading: without the package, this page does not know.
    expect($response->getContent())->not->toContain('data-suppression=');
});

it('serves a page with nothing but the token', function () {
    config()->set('preference-center.sources.marketing', false);
    config()->set('preference-center.sources.notifications', false);
    config()->set('preference-center.sources.suppression', false);

    $user = new FixtureUser(['email' => 'jane@example.com']);
    $user->setAttribute('id', 4711);

    $this->actingAs($user)->get(route('preference-center.show'))
        ->assertOk()
        ->assertSee(__('preference-center::public.nothing_installed'));
});

it('does not mount the token route where marketing is absent', function () {
    // The route's brand middleware names a marketing model. Registering it
    // anyway would move the failure from boot, where it is visible, to the
    // first request, where it is a 500 for one visitor.
    config()->set('preference-center.sources.marketing', false);

    $router = new \Illuminate\Routing\Router(app('events'), app());

    \Illuminate\Support\Facades\Route::swap($router);
    require __DIR__.'/../../routes/web.php';

    $names = collect($router->getRoutes())->map(fn ($route) => $route->getName())->filter()->values()->all();

    expect($names)->toContain('preference-center.show')
        ->and($names)->not->toContain('preference-center.token');
});
