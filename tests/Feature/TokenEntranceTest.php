<?php

use Goldnead\Marketing\Models\Subscription;
use Goldnead\Notifications\Models\NotificationPreference;
use Goldnead\PreferenceCenter\Tests\Fixtures\World;

/**
 * Door one: the token from a marketing mail.
 *
 * L15 settled the scope question — a token holder sees and changes everything,
 * not only their lists — so these cases are about what "everything" turns out
 * to include, and where it stops.
 */
beforeEach(function () {
    $this->lists = World::lists();
    World::types();

    $this->subscription = World::subscriber('jane@example.com', ['newsletter', 'events'], $this->lists);
    $this->token = $this->subscription->token;
});

it('shows all three blocks to a token holder, not only the lists', function () {
    $response = $this->get(route('preference-center.token', $this->token))->assertOk();

    // The lists the token's own subscription does not name are here too: the
    // point of the page is that an abmelde-link was a dead end which did not
    // reveal that the person is on four other lists of the same brand.
    expect(renderedLists($response->getContent()))->toBe([
        'angebote' => 'inactive',
        'chorleitung' => 'inactive',
        'events' => 'active',
        'newsletter' => 'active',
        'saenger' => 'inactive',
    ]);

    expect(renderedCells($response->getContent()))->toHaveKeys([
        'community.mention.mail',
        'event.reminder.digest',
        'account.security.mail',
    ]);

    // Nothing has been chosen, and the defaults are not uniform: one type
    // mails as it happens, another collects. The control says so rather than
    // rounding the state to the nearest of its four words.
    expect(renderedFrequency($response->getContent()))->toBe('mixed');

    $response->assertSee(__('preference-center::public.proof_unsubscribe_token'));
});

it('changes a list and a notification through the same submission', function () {
    $this->followingRedirects()
        ->post(route('preference-center.token.update', $this->token), [
            'action' => 'save',
            'blocks' => ['lists', 'types'],
            'lists' => ['newsletter', 'chorleitung'],
            'types' => ['community.mention' => ['in_app' => '1']],
        ])
        ->assertOk();

    expect(Subscription::query()->where('list_handle', 'chorleitung')->first()?->isSubscribed())->toBeTrue()
        ->and(Subscription::query()->where('list_handle', 'events')->first()?->isSubscribed())->toBeFalse();

    // `mail` was on by default for this type and was not posted back, so it is
    // switched off — checkbox semantics, the same rule the list block follows.
    $stored = NotificationPreference::query()
        ->where('contact_uuid', $this->subscription->refresh()->contact_uuid)
        ->where('type', 'community.mention')
        ->where('channel', 'mail')
        ->first();

    expect($stored)->not->toBeNull()
        ->and($stored->enabled)->toBeFalse()
        ->and($stored->uniqueness_key)->not->toBe('');
});

it('ends every list of the brand at once, and leaves the notifications alone', function () {
    $before = NotificationPreference::query()->count();

    $this->followingRedirects()
        ->post(route('preference-center.token.update', $this->token), ['action' => 'unsubscribe_all'])
        ->assertOk()
        ->assertSee(trans_choice('preference-center::public.all_done', 2, ['count' => 2]));

    expect(Subscription::query()->where('status', Subscription::STATUS_SUBSCRIBED)->count())->toBe(0)
        ->and(NotificationPreference::query()->count())->toBe($before);
});

it('answers 404 for a token nobody issued', function () {
    $this->get(route('preference-center.token', 'not-a-token'))->assertNotFound();
});

it('names its own route parameters so a sibling binding cannot claim them', function () {
    // A `Route::bind()` is application-wide. `{token}` and `{link}` are exactly
    // the names a sibling in this family would reach for, so this addon uses
    // neither — it uses `pcToken` and `pcLink`.
    $uris = collect(app('router')->getRoutes())
        ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'preference-center.'))
        ->flatMap(fn ($route) => $route->parameterNames())
        ->unique()
        ->values()
        ->all();

    expect($uris)->toBe(['pcLink', 'pcToken']);
});
