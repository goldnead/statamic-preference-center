<?php

use Goldnead\PreferenceCenter\Contracts\SenderIdentityResolver;
use Goldnead\PreferenceCenter\MagicLink\MagicLinkRequests;
use Goldnead\PreferenceCenter\Sending\SaidRecently;
use Goldnead\PreferenceCenter\Sending\SenderIdentity;
use Goldnead\PreferenceCenter\Tests\Fixtures\World;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Who a magic link goes out as, and over which transport.
 *
 * `Mail::fake()` is deliberately NOT used here. The fake records the mailable
 * but never renders it, and the From is decided during the render — so it can
 * prove the transport and not the sender, which is exactly one half of the bug.
 * Each brand gets its own `array` transport instead, and the assertions read
 * the real MIME message out of whichever transport accepted it.
 *
 * The bug: `Mail::to($email)->send(new MagicLinkMail($links))` — one mail, all
 * brands' links, process-wide default mailer, process-wide From. On a
 * multi-brand host a relay that verifies sending domains per account refuses
 * that From or rewrites it to its own, so a person asking brand A about their
 * own settings got a mail from brand B.
 */
beforeEach(function (): void {
    SaidRecently::forget();

    config()->set('mail.mailers.marke_a', ['transport' => 'array']);
    config()->set('mail.mailers.marke_b', ['transport' => 'array']);
    config()->set('mail.mailers.global', ['transport' => 'array']);
    config()->set('mail.default', 'global');
    config()->set('mail.from', ['address' => 'global@example.com', 'name' => 'Global']);

    $this->mails = fn (string $mailer) => collect(Mail::mailer($mailer)->getSymfonyTransport()->messages())
        ->map(fn ($sent) => $sent->getOriginalMessage())
        ->values()
        ->all();

    $this->ask = fn (string $email) => app(MagicLinkRequests::class)->request($email, '203.0.113.1');
});

/**
 * The line that keeps this package installable outside the host it was written
 * for. A single-brand install must send the mail it always sent — including the
 * package's own configured From, which is a value `MagicLinkMail::build()` sets
 * and no brand identity is competing with.
 */
it('leaves a single-brand install sending exactly as before', function (): void {
    World::lists();
    World::subscriber('jane@example.com', ['newsletter'], World::lists());

    config()->set('preference-center.magic_link.from', ['address' => 'links@example.test', 'name' => 'Links']);

    expect(($this->ask)('jane@example.com'))->toBe('sent');

    $mails = ($this->mails)('global');

    expect($mails)->toHaveCount(1)
        ->and($mails[0]->getFrom()[0]->getAddress())->toBe('links@example.test');
});

/**
 * THE test. Two brands know the same address, one process. Against the old code
 * this was a single mail out of the `global` transport carrying both links.
 */
it('sends each brand its own mail, from its own address, over its own transport', function (): void {
    $this->enableMultiBrand();

    $a = $this->makeBrand('marke-a', 'Marke A');
    $b = $this->makeBrand('marke-b', 'Marke B');

    $a->update(['settings' => ['mail' => [
        'from_address' => 'noreply@marke-a.test',
        'from_name' => 'Marke A',
        'mailer' => 'marke_a',
    ]]]);

    $b->update(['settings' => ['mail' => [
        'from_address' => 'noreply@marke-b.test',
        'mailer' => 'marke_b',
    ]]]);

    // Distinct list handles per brand, because `marketing_lists.handle` is
    // globally unique rather than unique per brand. A known gap in the
    // marketing addon, and nothing this file is about.
    foreach (['marke-a' => 'newsletter', 'marke-b' => 'chorleitung'] as $handle => $list) {
        $this->inBrand($handle, function () use ($list): void {
            World::subscriber('jane@example.com', [$list], World::lists([$list]));
        });
    }

    expect(($this->ask)('jane@example.com'))->toBe('sent');

    $fromA = ($this->mails)('marke_a');
    $fromB = ($this->mails)('marke_b');

    expect($fromA)->toHaveCount(1)
        ->and($fromA[0]->getFrom()[0]->getAddress())->toBe('noreply@marke-a.test')
        ->and($fromA[0]->getFrom()[0]->getName())->toBe('Marke A')
        ->and($fromB)->toHaveCount(1)
        ->and($fromB[0]->getFrom()[0]->getAddress())->toBe('noreply@marke-b.test')
        // The negative half, and the one that matters: the default transport
        // never saw a thing.
        ->and(($this->mails)('global'))->toHaveCount(0);

    // Each mail carries only its own brand's link, and no other brand's. A mail
    // that lists a second brand's link is the same lie in a different place —
    // and it is what the old single-mail path did by construction.
    $tokens = function ($message): array {
        preg_match_all('#preference-center/link/([A-Za-z0-9._~+/=-]+)#', (string) $message->getHtmlBody(), $m);

        return array_values(array_unique($m[1]));
    };

    expect($tokens($fromA[0]))->toHaveCount(1)
        ->and($tokens($fromB[0]))->toHaveCount(1)
        ->and($tokens($fromA[0])[0])->not->toBe($tokens($fromB[0])[0]);
});

/**
 * The brand identity is set on the mailable before `build()` runs. Without the
 * guard in `MagicLinkMail::build()` the package's own configured From would
 * quietly win it back — and the address is the half the relay checks against
 * the account the transport belongs to.
 */
it('does not let the package config override a brand address', function (): void {
    $this->enableMultiBrand();

    config()->set('preference-center.magic_link.from', ['address' => 'links@example.test', 'name' => 'Links']);

    $a = $this->makeBrand('marke-a', 'Marke A');
    $a->update(['settings' => ['mail' => [
        'from_address' => 'noreply@marke-a.test',
        'mailer' => 'marke_a',
    ]]]);

    $this->inBrand('marke-a', function (): void {
        World::subscriber('jane@example.com', ['newsletter'], World::lists(['newsletter']));
    });

    ($this->ask)('jane@example.com');

    $mails = ($this->mails)('marke_a');

    expect($mails)->toHaveCount(1)
        ->and($mails[0]->getFrom()[0]->getAddress())->toBe('noreply@marke-a.test');
});

it('sends nothing for a brand that declares mail settings without a from address', function (): void {
    $this->enableMultiBrand();

    Log::spy();

    $a = $this->makeBrand('halb', 'Halb');
    $a->update(['settings' => ['mail' => ['mailer' => 'marke_a']]]);

    $this->inBrand('halb', function (): void {
        World::subscriber('jane@example.com', ['newsletter'], World::lists(['newsletter']));
    });

    // Not `blocked`: nobody suppressed this address. The outcome is distinct in
    // the log for the same reason the others are, and like all of them it never
    // reaches the visitor — the page says the same sentence either way.
    expect(($this->ask)('jane@example.com'))->toBe('misconfigured')
        ->and(($this->mails)('marke_a'))->toHaveCount(0)
        ->and(($this->mails)('global'))->toHaveCount(0);

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message) => str_contains($message, 'from_address'))
        ->once();
});

/**
 * One broken brand must not silence the working ones. The person asked about
 * their settings; the brands that can answer, answer.
 */
it('still mails the brands that can send when one cannot', function (): void {
    $this->enableMultiBrand();

    $a = $this->makeBrand('halb', 'Halb');
    $a->update(['settings' => ['mail' => ['mailer' => 'marke_a']]]);

    $b = $this->makeBrand('marke-b', 'Marke B');
    $b->update(['settings' => ['mail' => [
        'from_address' => 'noreply@marke-b.test',
        'mailer' => 'marke_b',
    ]]]);

    foreach (['halb' => 'newsletter', 'marke-b' => 'chorleitung'] as $handle => $list) {
        $this->inBrand($handle, function () use ($list): void {
            World::subscriber('jane@example.com', [$list], World::lists([$list]));
        });
    }

    expect(($this->ask)('jane@example.com'))->toBe('sent')
        ->and(($this->mails)('marke_b'))->toHaveCount(1)
        ->and(($this->mails)('global'))->toHaveCount(0);
});

it('sends nothing for a brand naming a mailer config does not define', function (): void {
    $this->enableMultiBrand();

    Log::spy();

    $a = $this->makeBrand('tippfehler', 'Tippfehler');
    $a->update(['settings' => ['mail' => [
        'from_address' => 'noreply@tippfehler.test',
        'mailer' => 'scaleway_typo',
    ]]]);

    $this->inBrand('tippfehler', function (): void {
        World::subscriber('jane@example.com', ['newsletter'], World::lists(['newsletter']));
    });

    expect(($this->ask)('jane@example.com'))->toBe('misconfigured')
        ->and(($this->mails)('global'))->toHaveCount(0);

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message) => str_contains($message, 'scaleway_typo'))
        ->once();
});

it('lets a host swap the resolver without touching the addon', function (): void {
    World::subscriber('jane@example.com', ['newsletter'], World::lists(['newsletter']));

    app()->bind(
        SenderIdentityResolver::class,
        fn () => new class implements SenderIdentityResolver
        {
            public function resolve(?int $brandId): SenderIdentity
            {
                return SenderIdentity::of('marke_b', 'host@example.test', 'Host');
            }
        },
    );

    ($this->ask)('jane@example.com');

    $mails = ($this->mails)('marke_b');

    expect($mails)->toHaveCount(1)
        ->and($mails[0]->getFrom()[0]->getAddress())->toBe('host@example.test');
});

it('does not touch mail config while sending', function (): void {
    $this->enableMultiBrand();

    $a = $this->makeBrand('marke-a', 'Marke A');
    $a->update(['settings' => ['mail' => [
        'from_address' => 'noreply@marke-a.test',
        'mailer' => 'marke_a',
    ]]]);

    $this->inBrand('marke-a', function (): void {
        World::subscriber('jane@example.com', ['newsletter'], World::lists(['newsletter']));
    });

    ($this->ask)('jane@example.com');

    // Not cosmetics. A `Config::set('mail.from.…')` here would survive its own
    // `finally`, because Laravel has already burned the value into the cached
    // mailer instance by the time the window closes.
    expect(config('mail.from.address'))->toBe('global@example.com')
        ->and(config('mail.default'))->toBe('global');
});
