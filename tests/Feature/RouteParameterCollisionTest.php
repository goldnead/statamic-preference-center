<?php

use Goldnead\PreferenceCenter\Tests\Fixtures\World;
use Illuminate\Support\Facades\Route;

/**
 * A route-model binding is application-wide, not per package.
 *
 * A binding a sibling addon registers for `{token}` or `{link}` applies to every
 * route with that parameter name in every installed package, and resolves the
 * value against a repository that has never heard of it — so the route 404s
 * with nothing in any log to say why. That is exactly how
 * `goldnead/statamic-leadhub` 1.8.0 shipped a delete button that did nothing.
 *
 * This addon therefore owns no generic parameter name.
 */
beforeEach(function () {
    $this->lists = World::lists();
    World::types();
    $this->token = World::subscriber('jane@example.com', ['newsletter'], $this->lists)->token;
});

it('keeps working when a sibling claims the obvious names', function () {
    // What a sibling's binding looks like from here: it never sees our values,
    // so it aborts.
    Route::bind('token', fn () => abort(404, 'claimed by a sibling'));
    Route::bind('link', fn () => abort(404, 'claimed by a sibling'));

    $this->get(route('preference-center.token', $this->token))->assertOk();
    $this->get(route('preference-center.request'))->assertOk();
});

it('claims no name a sibling would plausibly reach for', function () {
    $ours = collect(app('router')->getRoutes())
        ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'preference-center.'))
        ->flatMap(fn ($route) => $route->parameterNames())
        ->unique()
        ->values()
        ->all();

    expect(array_intersect($ours, \Goldnead\PreferenceCenter\Tests\TestCase::NAMES_A_SIBLING_MIGHT_USE))->toBe([])
        ->and($ours)->toBe(['pcLink', 'pcToken']);
});
