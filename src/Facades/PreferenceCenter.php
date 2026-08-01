<?php

namespace Goldnead\PreferenceCenter\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Goldnead\PreferenceCenter\Data\PreferenceView view(\Goldnead\PreferenceCenter\Data\Access $access)
 * @method static object|null marketingCenter(\Goldnead\PreferenceCenter\Data\Access $access)
 * @method static string|null urlForToken(string $token)
 * @method static string|null requestUrl()
 *
 * @see \Goldnead\PreferenceCenter\PreferenceCenter
 *
 * Not the class to probe for. `method_exists()` on a facade is false for every
 * method it forwards through `__callStatic`, including the two above. A sibling
 * deciding whether this package is installed asks
 * `class_exists(\Goldnead\PreferenceCenter\PreferenceCenter::class)`, or at the
 * very least `PreferenceCenter::getFacadeRoot()`. See that class for the
 * discovery contract in full.
 */
class PreferenceCenter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'preference-center';
    }
}
