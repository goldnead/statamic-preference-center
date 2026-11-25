<?php

use Goldnead\Marketing\Models\Subscription;
use Goldnead\PreferenceCenter\Events\PreferencesChanged;
use Goldnead\PreferenceCenter\Sources\NotificationsSource;
use Goldnead\PreferenceCenter\Tests\Fixtures\World;
use Illuminate\Support\Facades\Event as Events;

/**
 * A submission that writes one block and then fails on the next has still
 * changed the first one. The proof has to name that change: the announce step
 * used to run only after every write had succeeded, so a later failure left a
 * stored change with no record of who authorised it.
 */
beforeEach(function () {
    $this->lists = World::lists();
    World::types();
    $this->token = World::subscriber('jane@example.com', ['newsletter'], $this->lists)->token;
});

it('announces the list change that was stored before a later write failed', function () {
    // The notifications write is the second block; it fails after the list
    // write has gone through.
    $real = app(NotificationsSource::class);
    $source = Mockery::mock($real)->makePartial();
    $source->shouldReceive('set')->andThrow(new RuntimeException('notifications down'));
    app()->instance(NotificationsSource::class, $source);

    Events::fake([PreferencesChanged::class]);
    $this->withoutExceptionHandling();

    expect(fn () => $this->post(route('preference-center.token.update', $this->token), [
        'action' => 'save',
        'blocks' => ['lists', 'types'],
        'lists' => ['chorleitung'],
        'types' => ['community.mention' => ['in_app' => '1']],
    ]))->toThrow(RuntimeException::class);

    expect(Subscription::query()->where('list_handle', 'chorleitung')->first()?->isSubscribed())->toBeTrue();

    Events::assertDispatched(PreferencesChanged::class, fn ($event) => $event->consentProof() === 'unsubscribe_token'
        && collect($event->changes)->contains(fn ($c) => $c['block'] === 'lists' && $c['target'] === 'chorleitung'));
});
