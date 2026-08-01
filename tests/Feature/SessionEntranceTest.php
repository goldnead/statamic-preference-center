<?php

use Goldnead\IdentityContracts\Facades\IdentityContext;
use Goldnead\Notifications\Models\NotificationPreference;
use Goldnead\PreferenceCenter\Tests\Fixtures\FixtureUser;
use Goldnead\PreferenceCenter\Tests\Fixtures\World;

/**
 * Door three: an authenticated session.
 *
 * The interesting property is not that it works. It is *which* identity it
 * stores against — `notification_preferences` matches `user_id` AND
 * `contact_uuid` with `=`, never OR, so a page that helpfully attached a
 * contact uuid the sender does not know about would write preferences into a
 * row nothing ever reads.
 */
beforeEach(function () {
    $this->lists = World::lists();
    World::types();
    World::subscriber('jane@example.com', ['newsletter'], $this->lists);

    $this->user = new FixtureUser(['id' => 4711, 'email' => 'jane@example.com', 'name' => 'Jane']);
    $this->user->setAttribute('id', 4711);
});

it('shows the page to a signed-in person, with the session as the proof', function () {
    $response = $this->actingAs($this->user)
        ->get(route('preference-center.show'))
        ->assertOk();

    expect($response->getContent())->toContain('data-proof="session"')
        ->and(renderedLists($response->getContent()))->toHaveKey('newsletter');
});

it('stores against exactly the identity the sender would resolve, and no more', function () {
    $this->actingAs($this->user)->followingRedirects()
        ->post(route('preference-center.update'), [
            'action' => 'save',
            'blocks' => ['types'],
            'types' => ['community.mention' => ['in_app' => '1']],
        ])->assertOk();

    $row = NotificationPreference::query()
        ->where('type', 'community.mention')->where('channel', 'mail')->firstOrFail();

    // What `IdentityContext::resolve($user)` produced, unimproved: a user id,
    // and a contact uuid only if the application itself binds a locator that
    // supplies one. This installation binds none, so it stays null — and the
    // sending path, which resolves the same way, will read the same row.
    expect((string) $row->user_id)->toBe('4711')
        ->and($row->contact_uuid)->toBeNull()
        ->and($row->uniqueness_key)->toHaveLength(64);

    $identity = IdentityContext::resolve($this->user);

    // Every row this page wrote is a row the sender's own scope reads back.
    // Not "some of them": a single row keyed differently is a preference the
    // person set and the product will never honour.
    expect(NotificationPreference::query()->forRecipient($identity)->count())
        ->toBe(NotificationPreference::query()->count())
        ->toBeGreaterThan(0);
});

it('is a 404 for a visitor with neither a session nor a link', function () {
    $this->get(route('preference-center.show'))->assertNotFound();
});
