<?php

use Goldnead\PreferenceCenter\Tests\Fixtures\World;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;

/**
 * The mail itself, as it leaves the machine.
 *
 * Nothing here is faked. `Mail::fake()` records mailables; it never builds a
 * MIME message, so it cannot see that a mail has one part instead of two, and
 * that is exactly the defect this file exists for: v1.0.0 sent `text/plain`
 * only, with a three-hundred-character signed URL as running text. Mailpit
 * measured HTML length 0. Every client that does not linkify left the person
 * with a URL to reassemble by hand.
 *
 * So these tests read the Symfony message out of the array transport, which is
 * the same object the SMTP transport would have written to a socket.
 */
beforeEach(function () {
    $this->lists = World::lists();
    World::types();
    World::subscriber('jane@example.com', ['newsletter'], $this->lists);

    Mail::mailer()->getSymfonyTransport()->flush();
});

/** The one message the transport actually built. */
function sentMessage(): Email
{
    $messages = Mail::mailer()->getSymfonyTransport()->messages();

    expect($messages)->toHaveCount(1);

    return $messages[0]->getOriginalMessage();
}

/**
 * The link a mail client would open, out of an `href`.
 *
 * Decoded, because an attribute value is an HTML context and `&amp;` is how a
 * `&` is spelled inside one. A test that skipped the decoding would be testing
 * the template's spelling instead of the link's behaviour.
 */
function hrefFromHtml(string $html): ?string
{
    return preg_match('#href="([^"]*preference-center/link/[^"]+)"#', $html, $m)
        ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')
        : null;
}

function urlFromText(string $text): ?string
{
    return preg_match('#(https?://\S*preference-center/link/\S+)#', $text, $m) ? rtrim($m[1]) : null;
}

it('sends both bodies, in one multipart message', function () {
    $this->post(route('preference-center.request.send'), ['email' => 'jane@example.com']);

    $message = sentMessage();

    expect($message->getTextBody())->toBeString()->not->toBe('')
        ->and($message->getHtmlBody())->toBeString()->not->toBe('')
        ->and($message->toString())->toContain('multipart/alternative');
});

it('puts a link in the plain-text body that actually opens', function () {
    // Not "contains a URL". The exact URL, character for character, and then
    // followed. A plain-text body has no HTML context to escape into: Blade's
    // default turned the `&` before `signature` into `&amp;`, the link read
    // perfectly to a human, and Laravel answered 403 because the signature no
    // longer matched. Found on the QA hub, not in this suite — which is why the
    // assertion follows the link rather than reading it.
    $this->post(route('preference-center.request.send'), ['email' => 'jane@example.com']);

    $text = sentMessage()->getTextBody();

    expect($text)->not->toContain('&amp;');

    $this->flushSession();
    $this->get(urlFromText($text))->assertRedirect(route('preference-center.show'));
});

it('puts a link in the HTML body that actually opens', function () {
    // The same proof for the other half, and the reason it needs its own: the
    // HTML body must escape what the text body must not, so "does not contain
    // &amp;" is the wrong check here and following the link is the only one
    // that means anything. This is the shape of the v1.0.0 regression, mirrored.
    $this->post(route('preference-center.request.send'), ['email' => 'jane@example.com']);

    $html = sentMessage()->getHtmlBody();
    $href = hrefFromHtml($html);

    expect($href)->toBeString()
        ->and($href)->toBe(urlFromText(sentMessage()->getTextBody()));

    $this->flushSession();
    $this->get($href)->assertRedirect(route('preference-center.show'));

    // And the page it lands on is the one that was asked for.
    $this->get(route('preference-center.show'))->assertOk()
        ->assertSee('data-proof="magic_link"', false);
});

it('carries the headers a host configured to keep the provider off the links', function () {
    // The other half of the click-tracking answer, and the one that fixes the
    // cause rather than surviving it: most providers take a per-message header
    // that stops them rewriting `href`s at all. The package names no provider —
    // whatever is in the map goes on the message verbatim.
    config()->set('preference-center.delivery.mail_headers', [
        'X-Mailgun-Track-Clicks' => 'no',
        'X-PM-TrackLinks' => 'None',
        'X-Mailjet-TrackClick' => 0,
    ]);

    $this->post(route('preference-center.request.send'), ['email' => 'jane@example.com']);

    $headers = sentMessage()->getHeaders();

    expect($headers->get('X-Mailgun-Track-Clicks')?->getBodyAsString())->toBe('no')
        ->and($headers->get('X-PM-TrackLinks')?->getBodyAsString())->toBe('None')
        ->and($headers->get('X-Mailjet-TrackClick')?->getBodyAsString())->toBe('0');
});

it('adds no headers of its own when none were configured', function () {
    // The default is empty on purpose. An addon that guessed the provider and
    // changed how it behaves would be a worse neighbour than one that asks.
    expect(config('preference-center.delivery.mail_headers'))->toBe([]);

    $this->post(route('preference-center.request.send'), ['email' => 'jane@example.com']);

    $names = array_map('strtolower', sentMessage()->getHeaders()->getNames());

    expect(array_filter($names, fn ($n) => str_starts_with($n, 'x-')))->toBe([]);
});

it('ignores a configured header that could not be written', function () {
    config()->set('preference-center.delivery.mail_headers', [
        'X-Mailgun-Track-Clicks' => 'no',
        'X-Broken' => ['array'],
        '' => 'nameless',
        'X-Also-Broken' => null,
    ]);

    $this->post(route('preference-center.request.send'), ['email' => 'jane@example.com']);

    $headers = sentMessage()->getHeaders();

    expect($headers->get('X-Mailgun-Track-Clicks')?->getBodyAsString())->toBe('no')
        ->and($headers->has('X-Broken'))->toBeFalse()
        ->and($headers->has('X-Also-Broken'))->toBeFalse();
});

it('is still clickable when one address belongs to more than one brand', function () {
    $this->enableMultiBrand();
    $this->makeBrand('default', 'Default');
    $this->makeBrand('second', 'Second');

    foreach (['default', 'second'] as $handle) {
        $this->inBrand($handle, function () use ($handle) {
            $lists = World::lists([$handle.'-news']);
            World::subscriber('in-both@example.com', [$handle.'-news'], $lists);
        });
    }

    app('brand-context')->forget();

    $this->post(route('preference-center.request.send'), ['email' => 'in-both@example.com']);

    // Two mails since 12.08.2026, one per brand, because each has to leave
    // under its own From and its own transport — see the sibling case in
    // MagicLinkTest. What this file still guards is the property it always
    // guarded: whatever arrives, the links in it open.
    $messages = collect(Mail::mailer()->getSymfonyTransport()->messages())
        ->map(fn ($sent) => $sent->getOriginalMessage());

    expect($messages)->toHaveCount(2);

    $hrefs = [];

    foreach ($messages as $message) {
        preg_match_all('#href="([^"]*preference-center/link/[^"]+)"#', (string) $message->getHtmlBody(), $matches);

        // One link per mail, and it is the one the mail names its brand by.
        expect(array_unique($matches[1]))->toHaveCount(1);

        $hrefs[] = $matches[1][0];
    }

    $bodies = $messages->map(fn ($m) => (string) $m->getHtmlBody())->implode("\n");
    $texts = $messages->map(fn ($m) => (string) $m->getTextBody())->implode("\n");

    expect($bodies)->toContain('Default')
        ->and($bodies)->toContain('Second')
        ->and($texts)->toContain('Second');

    foreach ($hrefs as $href) {
        $this->flushSession();
        $this->get(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            ->assertRedirect(route('preference-center.show'));
    }
});
