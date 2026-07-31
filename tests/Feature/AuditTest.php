<?php

use Goldnead\Leadhub\Models\Event;
use Goldnead\PreferenceCenter\Events\PreferencesChanged;
use Goldnead\PreferenceCenter\MagicLink\MagicLinkMail;
use Goldnead\PreferenceCenter\Tests\Fixtures\FixtureUser;
use Goldnead\PreferenceCenter\Tests\Fixtures\World;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Support\Facades\Mail;

/**
 * The third limit from L15: every change carries the proof that authorised it.
 *
 * Marketing already records a consent proof for the list changes it makes, and
 * it records the only one it knew about — `unsubscribe_token` — because until
 * now that was the only way onto its page. This page has three doors, and a
 * record that names the wrong one is worse than no record: it is a record that
 * would be relied on.
 */
beforeEach(function () {
    $this->lists = World::lists();
    World::types();
    $this->token = World::subscriber('jane@example.com', ['newsletter'], $this->lists)->token;
});

it('names the token when the token is what got in', function () {
    Events::fake([PreferencesChanged::class]);

    $this->post(route('preference-center.token.update', $this->token), [
        'action' => 'save', 'blocks' => ['lists'], 'lists' => ['chorleitung'],
    ]);

    Events::assertDispatched(PreferencesChanged::class, fn ($event) => $event->consentProof() === 'unsubscribe_token'
        && collect($event->changes)->contains(fn ($c) => $c['block'] === 'lists' && $c['target'] === 'chorleitung'));
});

it('names the magic link when a magic link is what got in', function () {
    Mail::fake();
    $this->post(route('preference-center.request.send'), ['email' => 'jane@example.com']);

    $url = null;
    Mail::assertSent(MagicLinkMail::class, function ($mail) use (&$url) {
        $url = $mail->url();

        return true;
    });

    $this->flushSession();
    $this->get($url);

    Events::fake([PreferencesChanged::class]);

    $this->post(route('preference-center.update'), [
        'action' => 'save', 'blocks' => ['lists'], 'lists' => ['events'],
    ]);

    Events::assertDispatched(PreferencesChanged::class, fn ($event) => $event->consentProof() === 'magic_link');
});

it('names the session when somebody is signed in', function () {
    Events::fake([PreferencesChanged::class]);

    $user = new FixtureUser(['email' => 'jane@example.com', 'name' => 'Jane']);
    $user->setAttribute('id', 4711);

    $this->actingAs($user)->post(route('preference-center.update'), [
        'action' => 'save', 'blocks' => ['types'], 'types' => ['community.mention' => ['in_app' => '1']],
    ]);

    Events::assertDispatched(PreferencesChanged::class, fn ($event) => $event->consentProof() === 'session');
});

it('writes the proof onto the contact timeline, where a data-subject request looks', function () {
    $this->post(route('preference-center.token.update', $this->token), [
        'action' => 'save', 'blocks' => ['lists'], 'lists' => ['chorleitung', 'newsletter'],
    ]);

    $event = Event::query()->where('type', 'preference_center.changed')->latest('id')->first();

    expect($event)->not->toBeNull()
        ->and($event->payload['consent_proof'])->toBe('unsubscribe_token')
        ->and($event->payload['changes'])->toBeArray();

    // The identity is pseudonymised before it is written: the record has to say
    // who changed something, not repeat their name and address into a second
    // store that has its own retention.
    expect($event->payload['identity']['email'])->toBeNull()
        ->and($event->payload['identity']['name'])->toBeNull()
        ->and($event->payload['identity']['contact_uuid'])->not->toBeNull();
});

it('says nothing when nothing changed', function () {
    Events::fake([PreferencesChanged::class]);

    // An audit trail that logs every visit is an audit trail nobody reads.
    $this->post(route('preference-center.token.update', $this->token), [
        'action' => 'save', 'blocks' => ['lists'], 'lists' => ['newsletter'],
    ]);

    Events::assertNotDispatched(PreferencesChanged::class);
});
