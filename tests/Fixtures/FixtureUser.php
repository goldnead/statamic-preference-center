<?php

namespace Goldnead\PreferenceCenter\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A logged-in person, for the session door.
 *
 * Never persisted: `actingAs()` needs an `Authenticatable`, not a row, and this
 * package reads nothing from a users table. That is the point — the host owns
 * its user model, and the only thing that crosses the boundary is an `Identity`.
 */
class FixtureUser extends Authenticatable
{
    protected $guarded = [];

    public $timestamps = false;
}
