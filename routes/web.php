<?php

use Goldnead\BrandContext\Http\Middleware\SetBrandFromRouteValue;
use Goldnead\PreferenceCenter\Http\Controllers\MagicLinkController;
use Goldnead\PreferenceCenter\Http\Controllers\PreferenceCenterController;
use Goldnead\PreferenceCenter\Http\Middleware\SetBrandFromLinkSession;
use Goldnead\PreferenceCenter\MagicLink\TrackingParameters;
use Goldnead\PreferenceCenter\Sources\MarketingSource;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Preference centre — public routes
|--------------------------------------------------------------------------
|
| Every parameter name here is prefixed `pc`. That is not style. A
| `Route::bind()` is application-wide, not per package: a binding another addon
| registers for `{token}` or `{link}` applies to every route with that name in
| every installed addon, including this one, and resolves it against a
| repository that has never heard of these values. That is exactly how
| goldnead/statamic-leadhub 1.8.0 shipped a delete button that did nothing.
|
*/

Route::prefix(config('preference-center.routes.prefix', '!/preference-center'))
    ->middleware((array) config('preference-center.routes.middleware', ['web']))
    ->group(function () {

        // The magic link. Requesting one is public and unauthenticated by
        // definition — that is the whole point of it — which is why the service
        // behind it is throttled twice and says nothing about who exists.
        Route::get('/request', [MagicLinkController::class, 'form'])
            ->name('preference-center.request');

        Route::post('/request', [MagicLinkController::class, 'send'])
            ->name('preference-center.request.send');

        // The signature is checked while overlooking the parameters a mail
        // service provider appends on the way here — Brevo's `_se` and its
        // relatives, each named in `delivery.ignored_query_parameters` with the
        // provider that adds it. `TrackingParameters` says what that costs and
        // why it is affordable on this route and nowhere else; the short version
        // is that the payload is in the path and `expires` stays signed.
        Route::get('/link/{pcLink}', [MagicLinkController::class, 'open'])
            ->name('preference-center.link')
            ->middleware(ValidateSignature::absolute(TrackingParameters::ignored()));

        // The session entrance: a followed magic link, or an authenticated user.
        Route::get('/', [PreferenceCenterController::class, 'show'])
            ->name('preference-center.show')
            ->middleware(SetBrandFromLinkSession::class);

        Route::post('/', [PreferenceCenterController::class, 'update'])
            ->name('preference-center.update')
            ->middleware(SetBrandFromLinkSession::class);
    });

// The token entrance. Registered only where marketing is installed, because
// the middleware that derives the brand names one of its models — a route
// referring to a class that is not there would fail at the first request, not
// at boot, which is the worst of both.
if (app(MarketingSource::class)->available()) {
    Route::prefix(config('preference-center.routes.prefix', '!/preference-center'))
        ->middleware((array) config('preference-center.routes.middleware', ['web']))
        ->group(function () {
            $brandFromToken = SetBrandFromRouteValue::class.':'.MarketingSource::SUBSCRIPTION.',token,pcToken';

            Route::get('/t/{pcToken}', [PreferenceCenterController::class, 'showToken'])
                ->name('preference-center.token')
                ->middleware($brandFromToken);

            Route::post('/t/{pcToken}', [PreferenceCenterController::class, 'updateToken'])
                ->name('preference-center.token.update')
                ->middleware($brandFromToken);
        });
}
