<?php

namespace Goldnead\PreferenceCenter\Http\Middleware;

use Closure;
use Goldnead\BrandContext\Models\Brand;
use Goldnead\PreferenceCenter\Http\SessionAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A magic link opens the brand it was issued in.
 *
 * The brand was sealed into the link, next to the address, and it has to
 * survive the redirect that follows it — otherwise the page renders in whatever
 * brand the browser's session was already sitting in, which for a person who
 * has never seen the control panel is the default brand, and the lists it would
 * show would belong to somebody else's audience.
 *
 * Like `SetBrandFromRouteValue`, this never aborts. A stale or forged brand id
 * simply finds no brand and leaves the scope closed.
 */
class SetBrandFromLinkSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $manager = app('brand-context');

        if (! $manager->multiBrandEnabled() || ! $request->hasSession()) {
            return $next($request);
        }

        $brandId = $request->session()->get(SessionAccess::BRAND);

        if (! is_int($brandId) && ! ctype_digit((string) $brandId)) {
            return $next($request);
        }

        $brand = Brand::query()->find((int) $brandId);

        if ($brand !== null) {
            $manager->setCurrent($brand);
        }

        return $next($request);
    }
}
