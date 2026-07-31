<?php

use Goldnead\PreferenceCenter\MagicLink\MagicLinkMail;
use Goldnead\PreferenceCenter\MagicLink\TrackingParameters;
use Goldnead\PreferenceCenter\Tests\Fixtures\World;
use Illuminate\Support\Facades\Mail;

/**
 * The link as it arrives, not as it was sent.
 *
 * Nothing local could have found this. A mail sink stores what it was handed; a
 * mail service provider rewrites it. Brevo puts every `href` of the HTML part
 * through its own click counter, and when the counter forwards the reader it
 * appends `_se`, the recipient address in base64. Laravel signs the whole query
 * string, so the URL that arrives is not the URL that was signed and
 * `ValidateSignature` answers 403 — which is what a real run over Brevo
 * measured on staging, against a link that passed every test in this suite.
 *
 * These tests do to the URL what the provider does to it, then follow it.
 *
 * The other half of the file is the boundary of that concession. Ignoring a
 * parameter means giving it away, so the tests that matter most here are the
 * ones that still refuse: an unnamed parameter, a rewritten payload, a
 * rewritten `expires`, and a link past its time.
 */
beforeEach(function () {
    $this->lists = World::lists();
    World::types();
    World::subscriber('jane@example.com', ['newsletter'], $this->lists);
});

/** The signed URL out of the one mail that was sent. */
function trackedTestLink(): string
{
    Mail::fake();

    test()->post(route('preference-center.request.send'), ['email' => 'jane@example.com']);

    $url = '';

    Mail::assertSent(MagicLinkMail::class, function ($mail) use (&$url) {
        $url = $url ?: $mail->url();

        return true;
    });

    expect($url)->toContain('signature=');

    return $url;
}

/**
 * The URL as Brevo's redirector hands it on.
 *
 * The parameter goes in front of `expires` and `signature`, which is where the
 * staging capture had it. Position is not decoration: Laravel verifies the raw
 * query string in the order it arrived, so a test that appended at the end
 * would be testing a case the provider does not produce.
 */
function asBrevoForwardsIt(string $url, string $email = 'jane@example.com'): string
{
    [$path, $query] = array_pad(explode('?', $url, 2), 2, '');

    return $path.'?_se='.rtrim(strtr(base64_encode($email), '+/', '-_'), '=').'&'.$query;
}

/* ------------------------------------------------------ what must now work */

it('opens a link that a click counter has added its own parameter to', function () {
    $url = trackedTestLink();

    $this->flushSession();
    $this->get(asBrevoForwardsIt($url))->assertRedirect(route('preference-center.show'));

    $page = $this->get(route('preference-center.show'))->assertOk();
    expect($page->getContent())->toContain('data-proof="magic_link"');
});

it('opens a link tagged by the other providers on the list', function () {
    $url = trackedTestLink();

    // Brevo's Google-Analytics tagging and Mailchimp's ids, both appended to
    // links by the sending platform rather than by anybody's browser.
    $tagged = $url.'&utm_source=brevo&utm_medium=email&utm_campaign=preferences&mc_cid=abc123&mc_eid=def456';

    $this->flushSession();
    $this->get($tagged)->assertRedirect(route('preference-center.show'));
});

/* -------------------------------------------------- what must still refuse */

it('refuses a parameter nobody named', function () {
    $url = trackedTestLink();

    // The concession is a list, not a policy. An attacker who could append
    // anything at all would be back to an unsigned query string.
    $this->flushSession();
    $this->get($url.'&anything=1')->assertForbidden();

    $this->flushSession();
    $this->get($url.'&_sex=1')->assertForbidden();
});

it('refuses an edited payload even with a tracking parameter alongside it', function () {
    $url = asBrevoForwardsIt(trackedTestLink());

    $this->flushSession();
    $this->get(preg_replace('#/link/([^?]+)#', '/link/$1A', $url))->assertForbidden();
});

it('refuses an edited signature even with a tracking parameter alongside it', function () {
    $url = asBrevoForwardsIt(trackedTestLink());

    $this->flushSession();
    $this->get(preg_replace('/signature=(.)/', 'signature=$1'.'0', $url, 1))->assertForbidden();
});

it('refuses a link whose expiry was moved, which is why expires is not ignorable', function () {
    $url = asBrevoForwardsIt(trackedTestLink());

    // The danger of an ignore list, made concrete. If `expires` were on it, this
    // request would be a permanent magic link: the value is no longer covered by
    // the signature and `signatureHasNotExpired()` would happily read the
    // forged one.
    $moved = preg_replace_callback(
        '/expires=(\d+)/',
        fn ($m) => 'expires='.((int) $m[1] + 86400),
        $url,
    );

    expect($moved)->not->toBe($url);

    $this->flushSession();
    $this->get($moved)->assertForbidden();
});

it('still lets a link expire when a counter has rewritten it', function () {
    $url = asBrevoForwardsIt(trackedTestLink());

    $this->flushSession();
    $this->get($url)->assertRedirect(route('preference-center.show'));

    $this->travel(31)->minutes();
    $this->flushSession();
    $this->get($url)->assertForbidden();
});

/* ------------------------------------------------------------- the guard */

it('drops the two parameters that carry the guarantee, however they are configured', function () {
    config()->set('preference-center.delivery.ignored_query_parameters', [
        '_se', 'expires', 'EXPIRES', ' signature ', 'utm_source',
    ]);

    expect(TrackingParameters::ignored())->toBe(['_se', 'utm_source']);
});

it('drops names that would not survive being passed to the router', function () {
    config()->set('preference-center.delivery.ignored_query_parameters', [
        '_se',
        'one,two',      // two names wearing one name's clothes
        'with space',
        '',
        ['nested'],
        '_se',          // and the duplicate a hand-edited list collects
    ]);

    expect(TrackingParameters::ignored())->toBe(['_se']);
});

it('ships a list on which every entry is a parameter a mail provider adds', function () {
    // A regression fence around the list itself. It is short on purpose, and
    // the day somebody widens it is the day this reads differently.
    expect(TrackingParameters::ignored())->toBe([
        '_se',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'mc_cid', 'mc_eid',
        '_hsenc', '_hsmi',
        'mkt_tok',
    ]);
});
