<?php

use Goldnead\PreferenceCenter\MagicLink\MagicLinkMail;
use Goldnead\PreferenceCenter\Tests\Fixtures\World;
use Goldnead\Suppression\Reasons;
use Goldnead\Suppression\SuppressionService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Door two: the magic link, and the four ways it could have been a liability.
 *
 * It is the only door this package builds rather than borrows, so it is the
 * only one whose security is this addon's fault.
 */
beforeEach(function () {
    $this->lists = World::lists();
    World::types();
    World::subscriber('jane@example.com', ['newsletter'], $this->lists);
    Mail::fake();
});

/** The page a request lands on, with the one value that legitimately varies removed. */
function neutralBody(string $html): string
{
    return preg_replace('/name="_token" value="[^"]+"/', 'name="_token" value="TOKEN"', $html);
}

it('answers a known and an unknown address with byte-identical pages', function () {
    $known = $this->followingRedirects()
        ->post(route('preference-center.request.send'), ['email' => 'jane@example.com'])
        ->assertOk();

    $this->flushSession();

    $unknown = $this->followingRedirects()
        ->post(route('preference-center.request.send'), ['email' => 'nobody@example.com'])
        ->assertOk();

    // Not "similar". The same bytes — a difference of one word, one heading or
    // one hidden field is an address-verification service.
    expect(neutralBody($unknown->getContent()))->toBe(neutralBody($known->getContent()));

    Mail::assertSent(MagicLinkMail::class, 1);
    Mail::assertNotSent(MagicLinkMail::class, fn ($mail) => $mail->hasTo('nobody@example.com'));
});

it('takes at least as long for the address it has nothing to do with', function () {
    // Identical wording with a 12 ms "no such person" and a 340 ms "mail sent"
    // is an enumeration oracle with good manners. The floor is the other half.
    config()->set('preference-center.magic_link.min_response_ms', 300);

    $timed = function (string $email) {
        $started = microtime(true);
        $this->post(route('preference-center.request.send'), ['email' => $email]);

        return (microtime(true) - $started) * 1000;
    };

    $unknown = $timed('nobody@example.com');
    $this->flushSession();
    $known = $timed('jane@example.com');

    expect($unknown)->toBeGreaterThanOrEqual(295.0)
        ->and($known)->toBeGreaterThanOrEqual(295.0);
});

it('does not write to a mailbox that already gave up on us', function () {
    app(SuppressionService::class)->suppress('jane@example.com', Reasons::HARD_BOUNCE);

    $this->followingRedirects()
        ->post(route('preference-center.request.send'), ['email' => 'jane@example.com'])
        ->assertOk()
        ->assertSee(__('preference-center::public.magic_link_sent'));

    // "Here is your link to manage preferences" is still mail, and this address
    // is the one the provider told us to stop mailing.
    Mail::assertNothingSent();
});

it('stops one mailbox being flooded, and stops being an amplifier', function () {
    config()->set('preference-center.magic_link.throttle.per_address', ['max' => 2, 'decay_minutes' => 60]);
    config()->set('preference-center.magic_link.throttle.per_origin', ['max' => 3, 'decay_minutes' => 60]);

    World::subscriber('bob@example.com', ['newsletter'], $this->lists);
    World::subscriber('carol@example.com', ['newsletter'], $this->lists);

    foreach (['jane', 'jane', 'jane'] as $who) {
        $this->post(route('preference-center.request.send'), ['email' => $who.'@example.com']);
        $this->flushSession();
    }

    // Two got through, the third did not — per address.
    Mail::assertSent(MagicLinkMail::class, 2);

    // The origin limiter has counted all three. One more from anywhere, to
    // anyone, is refused: without it, a per-address limit still lets one client
    // mail ten thousand different people.
    $this->post(route('preference-center.request.send'), ['email' => 'bob@example.com']);

    Mail::assertSent(MagicLinkMail::class, 2);
});

it('counts an address nobody has heard of against the limit too', function () {
    // Counting only real addresses would make the limiter itself the oracle the
    // rest of this endpoint is built to avoid.
    config()->set('preference-center.magic_link.throttle.per_origin', ['max' => 2, 'decay_minutes' => 60]);

    RateLimiter::clear('preference-center:magic:origin:'.hash('sha256', '127.0.0.1'));

    $this->post(route('preference-center.request.send'), ['email' => 'ghost-one@example.com']);
    $this->flushSession();
    $this->post(route('preference-center.request.send'), ['email' => 'ghost-two@example.com']);
    $this->flushSession();
    $this->post(route('preference-center.request.send'), ['email' => 'jane@example.com']);

    Mail::assertNothingSent();
});

it('opens the page, and stops opening it once the link has expired', function () {
    Mail::fake();

    $this->post(route('preference-center.request.send'), ['email' => 'jane@example.com']);

    $url = null;
    Mail::assertSent(MagicLinkMail::class, function ($mail) use (&$url) {
        $url = $mail->url;

        return true;
    });

    expect($url)->toBeString()->and($url)->toContain('signature=');

    $this->flushSession();
    $this->get($url)->assertRedirect(route('preference-center.show'));

    $page = $this->get(route('preference-center.show'))->assertOk();
    expect($page->getContent())->toContain('data-proof="magic_link"');

    // Past the signature's own expiry. Laravel refuses it before this addon
    // sees it, which is the reason the lifetime lives in the URL and not in a
    // column somebody has to remember to prune.
    $this->travel(31)->minutes();
    $this->flushSession();
    $this->get($url)->assertForbidden();
});

it('refuses a link whose payload was edited', function () {
    Mail::fake();
    $this->post(route('preference-center.request.send'), ['email' => 'jane@example.com']);

    $url = null;
    Mail::assertSent(MagicLinkMail::class, function ($mail) use (&$url) {
        $url = $mail->url;

        return true;
    });

    $tampered = preg_replace('#/link/([^?]+)#', '/link/$1A', $url);

    $this->flushSession();
    $this->get($tampered)->assertForbidden();
});

it('will not mail a link to an address this installation has never seen', function () {
    $this->post(route('preference-center.request.send'), ['email' => 'stranger@example.com']);

    // Otherwise the endpoint mails a signed link to anything typed into it,
    // which is an open relay with extra steps.
    Mail::assertNothingSent();
});

it('searches the brand it was told to, and says the same thing either way', function () {
    // Every other public entrance derives its brand from something the visitor
    // could not choose. This one has nothing to derive from — an address is not
    // yet known to belong anywhere, which is the question being asked.
    $this->enableMultiBrand();
    $this->makeBrand('default', 'Default');
    $this->makeBrand('second', 'Second');

    $this->inBrand('second', function () {
        $lists = World::lists(['second-newsletter']);
        World::subscriber('only-in-second@example.com', ['second-newsletter'], $lists);
    });

    app('brand-context')->forget();

    // Without the hint the default brand is searched, and this address is not
    // in it. Same page, no mail.
    $without = $this->followingRedirects()
        ->post(route('preference-center.request.send'), ['email' => 'only-in-second@example.com'])
        ->assertOk();

    Mail::assertNothingSent();

    $this->flushSession();

    $with = $this->followingRedirects()
        ->post(route('preference-center.request.send'), [
            'email' => 'only-in-second@example.com',
            'pcBrand' => 'second',
        ])
        ->assertOk();

    Mail::assertSent(MagicLinkMail::class, 1);

    // And the page said the same thing both times — the hint changes which
    // audience is searched, never what is revealed.
    expect(neutralBody($with->getContent()))->toContain(__('preference-center::public.magic_link_sent'))
        ->and(neutralBody($without->getContent()))->toContain(__('preference-center::public.magic_link_sent'));

    $this->flushSession();
    app('brand-context')->forget();

    // A brand nobody has aborts nothing: it finds none, the current one stays,
    // and the page behaves exactly as it would have without the hint.
    $this->post(route('preference-center.request.send'), [
        'email' => 'only-in-second@example.com',
        'pcBrand' => 'no-such-brand',
    ])->assertRedirect();

    Mail::assertSent(MagicLinkMail::class, 1);
});

it('puts a link in the mail that actually opens', function () {
    // Not "contains a URL". The exact URL, character for character, and then
    // followed. Blade escapes by default, and a plain-text body has no HTML
    // context to escape into: the `&` before `signature` became `&amp;`, the
    // link read perfectly to a human, and Laravel answered 403 because the
    // signature no longer matched. Found on the QA hub, not in this suite —
    // which is why the assertion now follows the link rather than reading it.
    Mail::fake();
    $this->post(route('preference-center.request.send'), ['email' => 'jane@example.com']);

    $mail = null;
    Mail::assertSent(MagicLinkMail::class, function ($sent) use (&$mail) {
        $mail = $sent;

        return true;
    });

    $body = $mail->render();

    expect($body)->toContain($mail->url)
        ->and($body)->not->toContain('&amp;');

    $fromTheMail = preg_match('#(https?://\S*preference-center/link/\S+)#', $body, $m) ? rtrim($m[1]) : null;

    $this->flushSession();
    $this->get($fromTheMail)->assertRedirect(route('preference-center.show'));
});
