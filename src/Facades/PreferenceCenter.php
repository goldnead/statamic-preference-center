<?php

namespace Goldnead\PreferenceCenter\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Goldnead\PreferenceCenter\Data\PreferenceView view(\Goldnead\PreferenceCenter\Data\Access $access)
 * @method static object|null marketingCenter(\Goldnead\PreferenceCenter\Data\Access $access)
 *
 * @see \Goldnead\PreferenceCenter\PreferenceCenter
 */
class PreferenceCenter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'preference-center';
    }
}
