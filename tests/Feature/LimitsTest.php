<?php

use Goldnead\Marketing\Models\Subscription;
use Goldnead\Notifications\Models\NotificationPreference;
use Goldnead\PreferenceCenter\Tests\Fixtures\World;
use Goldnead\Suppression\Reasons;
use Goldnead\Suppression\SuppressionService;

/**
 * The three limits from L15, met at the write path.
 *
 * Every case here removes the `disabled` attribute the way a browser's
 * inspector does — by not sending it — and posts what the form would have sent
 * if the attribute had never been there. The assertion is always the same
 * shape: the page says no, and the database is unchanged.
 */
beforeEach(function () {
    $this->lists = World::lists();
    World::types();
    $this->subscription = World::subscriber('jane@example.com', ['newsletter', 'events'], $this->lists);
    $this->token = $this->subscription->token;
});

it('will not switch off a required type, however the request is shaped', function () {
    $response = $this->get(route('preference-center.token', $this->token))->assertOk();

    // Rendered as on and locked. `locked-on` and `on` are different states:
    // one may be turned off and the other may not, and a screenshot cannot
    // tell them apart.
    expect(renderedCells($response->getContent())['account.security.mail'])->toBe('locked-on');

    $this->followingRedirects()
        ->post(route('preference-center.token.update', $this->token), [
            'action' => 'save',
            'blocks' => ['types'],
            // account.security is simply absent — which is exactly what a
            // browser sends for an unchecked box.
            'types' => ['community.mention' => ['in_app' => '1', 'mail' => '1']],
        ])
        ->assertOk()
        ->assertSee(__('preference-center::public.refused_required'));

    expect(NotificationPreference::query()->where('type', 'account.security')->count())->toBe(0);
});

it('will not lift a block that exists only in the suppression table', function () {
    // Nothing is written to the contact here — a provider event lands in
    // `suppressions` and nowhere else — so a page that asked LeadHub alone
    // would see an ordinary subscriber and offer every list back.
    app(SuppressionService::class)->suppress('jane@example.com', Reasons::COMPLAINT);

    expect((bool) \Goldnead\Leadhub\Models\Contact::query()
        ->where('email', 'jane@example.com')->value('do_not_contact'))->toBeFalse();

    $this->followingRedirects()
        ->post(route('preference-center.token.update', $this->token), [
            'action' => 'save',
            'blocks' => ['lists', 'types', 'frequency'],
            'lists' => ['newsletter', 'chorleitung', 'saenger', 'events', 'angebote'],
            // search.alert has neither channel by default: switching one on is
            // a change, and a change to a mailbox that is closed.
            'types' => [
                'community.mention' => ['in_app' => '1', 'mail' => '1'],
                'event.reminder' => ['in_app' => '1', 'digest' => '1'],
                'search.alert' => ['in_app' => '1', 'mail' => '1', 'digest' => '1'],
            ],
            'frequency' => 'immediate',
        ])
        ->assertOk()
        ->assertSee(__('preference-center::public.refused_blocked'));

    expect(Subscription::query()->where('list_handle', 'angebote')->exists())->toBeFalse()
        ->and(NotificationPreference::query()->whereIn('channel', ['mail', 'digest'])->count())->toBe(0);

    $response = $this->get(route('preference-center.token', $this->token))->assertOk();

    expect(renderedLists($response->getContent()))->toBe([
        'angebote' => 'blocked',
        'chorleitung' => 'blocked',
        'events' => 'blocked',
        'newsletter' => 'blocked',
        'saenger' => 'blocked',
    ]);

    // The mailbox is closed, so the two channels that end in one are off and
    // locked. `in_app` is untouched: a page inside the product is not a mailbox.
    $cells = renderedCells($response->getContent());

    expect($cells['community.mention.mail'])->toBe('locked-on')
        ->and($cells['event.reminder.digest'])->toBe('locked-on')
        ->and($cells['search.alert.mail'])->toBe('locked-off')
        ->and($cells['community.mention.in_app'])->toBe('on');
});

it('leaves a blocked list exactly as it was, in both directions', function () {
    // Marketing's own rule, and this page inherits it rather than arguing with
    // it: a bounced or complained subscription is left alone even by
    // "unsubscribe from everything", because rewriting it to `unsubscribed`
    // would erase the reason it stopped — the one piece of state the sending
    // path relies on. The person is not receiving anything either way.
    app(SuppressionService::class)->suppress('jane@example.com', Reasons::HARD_BOUNCE);

    $before = Subscription::query()->orderBy('id')->pluck('status', 'list_handle')->all();

    $this->followingRedirects()
        ->post(route('preference-center.token.update', $this->token), ['action' => 'unsubscribe_all'])
        ->assertOk()
        ->assertSee(trans_choice('preference-center::public.all_done', 0, ['count' => 0]));

    expect(Subscription::query()->orderBy('id')->pluck('status', 'list_handle')->all())->toBe($before);

    // What a blocked person can still do is stop the product notifications,
    // because `never` means less mail rather than more.
    $this->followingRedirects()
        ->post(route('preference-center.token.update', $this->token), [
            'action' => 'save',
            'blocks' => ['frequency'],
            'frequency' => 'never',
        ])->assertOk();

    expect(NotificationPreference::query()->where('enabled', true)->whereIn('channel', ['mail', 'digest'])->count())->toBe(0)
        ->and(NotificationPreference::query()->whereIn('channel', ['mail', 'digest'])->count())->toBeGreaterThan(0);
});

it('writes nothing for a visitor it could not place', function () {
    // A pending sign-up has no contact yet, so there is no key to store a
    // notification preference against. `notification_preferences` matches
    // `user_id` AND `contact_uuid` with `=`; both NULL hashes to one key that
    // every unplaceable visitor would then share.
    Subscription::query()->update(['contact_uuid' => null]);
    \Goldnead\Leadhub\Models\Contact::query()->delete();

    $response = $this->get(route('preference-center.token', $this->token))->assertOk();

    expect(renderedCells($response->getContent())['community.mention.in_app'])->toBe('locked-on');

    $this->followingRedirects()
        ->post(route('preference-center.token.update', $this->token), [
            'action' => 'save',
            'blocks' => ['types'],
            'types' => ['community.mention' => ['mail' => '1']],
        ])
        ->assertOk()
        ->assertSee(__('preference-center::public.refused_unidentified'));

    expect(NotificationPreference::query()->count())->toBe(0);
});

it('ignores a type nobody registered', function () {
    $this->followingRedirects()
        ->post(route('preference-center.token.update', $this->token), [
            'action' => 'save',
            'blocks' => ['types'],
            'types' => ['invented.type' => ['mail' => '1']],
        ])
        ->assertOk()
        ->assertSee(__('preference-center::public.refused_unknown'));

    expect(NotificationPreference::query()->where('type', 'invented.type')->count())->toBe(0);
});
