<?php

namespace Goldnead\PreferenceCenter\Http;

use Goldnead\PreferenceCenter\Data\Access;
use Goldnead\PreferenceCenter\Identity\AccessResolver;
use Illuminate\Http\Request;

/**
 * What a followed magic link leaves behind.
 *
 * The link is signed and expiring, so it cannot be posted to — a form
 * submission would have to carry the signature back, and every redirect after
 * it would carry it further. Instead the link is spent once on arrival and
 * leaves a short-lived note in the session, with its own expiry, independent of
 * the session lifetime the host has configured for logged-in people.
 */
class SessionAccess
{
    public const EMAIL = 'preference-center.email';

    public const BRAND = 'preference-center.brand';

    public const EXPIRES = 'preference-center.expires';

    /** The three keys the note is made of. Everything that ends it uses this. */
    public const KEYS = [self::EMAIL, self::BRAND, self::EXPIRES];

    public function __construct(protected AccessResolver $resolver) {}

    /**
     * Spend the link and leave the note.
     *
     * The session id is regenerated first, and the old record destroyed with it.
     * Without that, whoever handed over the session id gets the note: a link
     * followed in a session somebody else fixed — a shared machine, a `?PHPSESSID`
     * in a forwarded URL, a cookie written by a neighbouring subdomain — writes
     * the address into *their* session, and the id they already hold now opens
     * the page. Measured on the QA hub against v1.0.0: the cookie captured
     * before the click, replayed from a separate browser context, answered 200
     * with a stranger's address on it.
     *
     * This is the same move `Auth::login()` makes, for the same reason. Anything
     * that grants access to a session has to give that session a new name.
     */
    public function open(Request $request, string $email, int $brandId): void
    {
        $request->session()->regenerate(true);

        $request->session()->put(self::EMAIL, $email);
        $request->session()->put(self::BRAND, $brandId);
        $request->session()->put(self::EXPIRES, now()
            ->addMinutes((int) config('preference-center.magic_link.session_minutes', 60))
            ->getTimestamp());
    }

    public function access(Request $request): ?Access
    {
        if (! $request->hasSession()) {
            return null;
        }

        $email = $request->session()->get(self::EMAIL);
        $expires = (int) $request->session()->get(self::EXPIRES, 0);

        if (! is_string($email) || $email === '') {
            return null;
        }

        if ($expires < now()->getTimestamp()) {
            $this->close($request);

            return null;
        }

        return $this->resolver->fromMagicLink($email);
    }

    public function close(Request $request): void
    {
        $request->session()->forget(self::KEYS);
    }
}
