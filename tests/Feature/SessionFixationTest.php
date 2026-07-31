<?php

use Goldnead\PreferenceCenter\Http\SessionAccess;
use Goldnead\PreferenceCenter\MagicLink\MagicLinkMail;
use Goldnead\PreferenceCenter\Tests\Fixtures\FixtureUser;
use Goldnead\PreferenceCenter\Tests\Fixtures\World;
use Illuminate\Auth\Events\Login;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Mail;

/**
 * Who else is holding this session.
 *
 * A magic link is spent on arrival and leaves a note in the session. That is
 * the whole mechanism, and it means the session id becomes the credential the
 * moment the link is clicked. Anything that hands somebody a session id they
 * did not create — a shared machine, a `?PHPSESSID` in a forwarded URL, a
 * cookie written by a neighbouring subdomain — therefore hands them the page,
 * for as long as the note lives.
 *
 * These tests do not check that `regenerate()` was called. They take the cookie
 * somebody could actually have taken, send it back, and read what comes out.
 * Against v1.0.0 on the QA hub that came out as HTTP 200 with a stranger's
 * address in the lede.
 */
beforeEach(function () {
    $this->lists = World::lists();
    World::types();
    World::subscriber('jane@example.com', ['newsletter'], $this->lists);

    Mail::fake();
});

/**
 * The session id a response handed to the browser.
 *
 * Decrypted here because the test harness encrypts on the way back in, exactly
 * as a browser's cookie would be decrypted on the way in. The value travelling
 * between the two is the cookie the response set, which is the thing an
 * attacker gets to keep.
 */
function sessionIdFrom($response): ?string
{
    foreach ($response->headers->getCookies() as $cookie) {
        if ($cookie->getName() === config('session.cookie')) {
            return CookieValuePrefix::remove(decrypt($cookie->getValue(), false));
        }
    }

    return null;
}

/** The next request, made from the browser holding this session id. */
function asBrowser($test, ?string $sessionId)
{
    $test->flushSession();

    return $test->withCookie(config('session.cookie'), (string) $sessionId);
}

function linkFor(string $email): string
{
    test()->post(route('preference-center.request.send'), ['email' => $email]);

    $url = null;
    Mail::assertSent(MagicLinkMail::class, function ($mail) use (&$url) {
        $url ??= $mail->url();

        return true;
    });

    return (string) $url;
}

it('will not open the page for a session id captured before the link was clicked', function () {
    $url = linkFor('jane@example.com');

    // What an attacker has: a session of their own making, before anything has
    // happened in it.
    $this->flushSession();
    $fixated = sessionIdFrom($this->get(route('preference-center.request')));

    expect($fixated)->toBeString();

    // The victim follows their link in exactly that session.
    asBrowser($this, $fixated)->get($url)->assertRedirect(route('preference-center.show'));

    // And the attacker, holding the id they planted, asks for the page.
    $replay = asBrowser($this, $fixated)->get(route('preference-center.show'));

    $replay->assertNotFound();
    expect($replay->getContent())->not->toContain('jane@example.com');
});

it('leaves the person who did click still holding a working session', function () {
    // The other half of the same change. A fix that threw the note away with
    // the old id would pass the test above and be a worse bug than the one it
    // closed, so the new cookie is followed to the page it is supposed to open.
    $url = linkFor('jane@example.com');

    $this->flushSession();
    $followed = $this->get($url)->assertRedirect(route('preference-center.show'));

    $page = asBrowser($this, sessionIdFrom($followed))
        ->get(route('preference-center.show'))
        ->assertOk();

    expect($page->getContent())->toContain('data-proof="magic_link"')
        ->and($page->getContent())->toContain('jane@example.com');
});

it('gives the click a session id the browser did not arrive with', function () {
    // Stated directly, because the two tests above would both survive a fix
    // that happened to work for another reason.
    $url = linkFor('jane@example.com');

    $this->flushSession();
    $before = sessionIdFrom($this->get(route('preference-center.request')));

    $after = sessionIdFrom(asBrowser($this, $before)->get($url));

    expect($after)->toBeString()->not->toBe($before);
});

it('ends the note when somebody signs in', function () {
    // The note outranks a logged-in session on purpose: whoever just followed a
    // link is asking about the address in that link. That is right for them and
    // wrong for the next person, and v1.0.0 could not tell them apart — the note
    // lived sixty minutes and nothing discarded it, so a colleague who signed in
    // at the same machine was shown, and could change, the first person's
    // settings. Measured on the QA hub with an authenticated control panel in
    // the same browser: `data-proof="magic_link"`, the other address in the lede.
    $url = linkFor('jane@example.com');

    $this->flushSession();
    $session = sessionIdFrom($this->get($url)->assertRedirect(route('preference-center.show')));

    asBrowser($this, $session)
        ->get(route('preference-center.show'))
        ->assertOk()
        ->assertSee('jane@example.com');

    // Somebody signs in, in that same browser. `SessionGuard::login()` migrates
    // the session itself — new id, same data, note included — so the browser
    // carries on with the cookie the sign-in handed back. Replaying the old one
    // would land on an empty session and pass this test for a reason that has
    // nothing to do with the fix.
    $signedIn = asBrowser($this, $session)->get('pc-test/sign-in')->assertOk();

    $after = asBrowser($this, sessionIdFrom($signedIn) ?? $session)
        ->get(route('preference-center.show'));

    expect($after->getContent())->not->toContain('jane@example.com')
        ->and($after->getContent())->not->toContain('data-proof="magic_link"');
});

it('does not end the note for somebody who was already signed in when they clicked', function () {
    // `Login` fires when somebody signs in. `Authenticated` fires on every
    // request that resolves a user, including every request by the person who
    // was already signed in before asking for their own link — listening to that
    // one would quietly reverse the ordering this package chose on purpose.
    $url = linkFor('jane@example.com');

    $this->flushSession();
    $session = sessionIdFrom($this->get($url)->assertRedirect(route('preference-center.show')));

    $page = asBrowser($this, $session)
        ->actingAs(new FixtureUser(['id' => 99, 'email' => 'someone-else@example.com']))
        ->get(route('preference-center.show'))
        ->assertOk();

    expect($page->getContent())->toContain('data-proof="magic_link"')
        ->and($page->getContent())->toContain('jane@example.com');
});

it('forgets the note the moment a login event is fired, whatever fired it', function () {
    // The same rule stated at the level of the event, for the hosts that sign
    // people in from somewhere this suite cannot route a request through.
    session()->put(SessionAccess::EMAIL, 'jane@example.com');
    session()->put(SessionAccess::EXPIRES, now()->addHour()->getTimestamp());

    event(new Login('web', new FixtureUser(['id' => 1, 'email' => 'someone@example.com']), false));

    expect(session()->has(SessionAccess::EMAIL))->toBeFalse()
        ->and(session()->has(SessionAccess::EXPIRES))->toBeFalse();
});
