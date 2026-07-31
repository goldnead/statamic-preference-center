<?php

namespace Goldnead\PreferenceCenter\Listeners;

use Goldnead\PreferenceCenter\Http\SessionAccess;
use Illuminate\Auth\Events\Login;

/**
 * A login ends the magic-link note.
 *
 * The note outranks an authenticated session on purpose: somebody who has just
 * opened a link they asked for is asking about the address in that link, which
 * need not be the address on the account they happen to still be logged into.
 * That order is right for the person who followed the link. It is wrong for the
 * *next* person, and v1.0.0 had no way to tell them apart: the note lived for
 * sixty minutes and nothing discarded it, so on a shared machine a colleague
 * could sign in with their own account and still be shown — and be able to
 * change — the first person's settings. Measured on the QA hub: an
 * authenticated control-panel session in the same browser, and the page still
 * stamped `data-proof="magic_link"` with the other address in the lede.
 *
 * A login is the one event that says "somebody else is here now". So it ends
 * the note, and the session door takes over.
 *
 * `Login`, deliberately, and not `Authenticated`: the latter fires on every
 * request that resolves a user, including the requests of a person who was
 * already signed in when they followed their own link. That is the case the
 * ordering exists for and it must survive.
 */
class EndTheNoteOnLogin
{
    public function handle(Login $event): void
    {
        // A login can also happen in a console command or a queued job, where
        // there is no session bound at all. Forgetting three keys from a session
        // that was never started is harmless; asking for one that does not exist
        // is not.
        if (! app()->bound('session.store')) {
            return;
        }

        app('session.store')->forget(SessionAccess::KEYS);
    }
}
