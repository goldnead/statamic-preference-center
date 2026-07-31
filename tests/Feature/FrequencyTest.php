<?php

use Goldnead\Notifications\Models\NotificationPreference;
use Goldnead\Notifications\Preferences\PreferenceResolver;
use Goldnead\PreferenceCenter\Frequency;
use Goldnead\PreferenceCenter\Tests\Fixtures\World;

/**
 * The cadence block: four words, over storage that holds two of them.
 *
 * `notification_preferences.frequency` accepts `daily` and `weekly` and nothing
 * else. `immediate` and `never` are expressed as the channel state they
 * describe, so all four are distinct in storage and every one of them reads
 * back as itself. If they were not distinct, this page would be able to show a
 * person a choice they did not make.
 */
beforeEach(function () {
    $this->lists = World::lists();
    World::types();
    $this->token = World::subscriber('jane@example.com', ['newsletter'], $this->lists)->token;
});

function chooseFrequency($test, string $token, string $choice)
{
    return $test->followingRedirects()->post(route('preference-center.token.update', $token), [
        'action' => 'save',
        'blocks' => ['frequency'],
        'frequency' => $choice,
    ])->assertOk();
}

it('round-trips every one of the four', function (string $choice) {
    $response = chooseFrequency($this, $this->token, $choice);

    expect(renderedFrequency($response->getContent()))->toBe($choice);

    // And again from a fresh render, so the answer is read out of storage
    // rather than out of the request that just went past.
    $fresh = $this->get(route('preference-center.token', $this->token))->assertOk();

    expect(renderedFrequency($fresh->getContent()))->toBe($choice);
})->with([Frequency::IMMEDIATE, Frequency::DAILY, Frequency::WEEKLY, Frequency::NEVER]);

it('stores the two cadences as a cadence and the other two as channel state', function () {
    chooseFrequency($this, $this->token, Frequency::DAILY);

    $identity = app(\Goldnead\PreferenceCenter\Identity\AccessResolver::class)
        ->fromMarketingToken($this->token)->identity;

    expect(app(PreferenceResolver::class)->digestFrequency($identity))->toBe('daily')
        ->and(NotificationPreference::query()->where('channel', 'digest')->where('enabled', true)->count())->toBeGreaterThan(0)
        ->and(NotificationPreference::query()->where('channel', 'mail')->where('enabled', true)->count())->toBe(0);

    chooseFrequency($this, $this->token, Frequency::IMMEDIATE);

    expect(NotificationPreference::query()->where('channel', 'digest')->where('enabled', true)->count())->toBe(0)
        ->and(NotificationPreference::query()->where('channel', 'mail')->where('enabled', true)->count())->toBeGreaterThan(0);

    chooseFrequency($this, $this->token, Frequency::NEVER);

    expect(NotificationPreference::query()->whereIn('channel', ['mail', 'digest'])->where('enabled', true)->count())->toBe(0);
});

it('never touches a required type, whichever cadence is chosen', function () {
    chooseFrequency($this, $this->token, Frequency::NEVER);

    // Account security is not a cadence question. `never` means "no optional
    // notification mail", and the page says so in those words.
    expect(NotificationPreference::query()->where('type', 'account.security')->count())->toBe(0);

    $identity = app(\Goldnead\PreferenceCenter\Identity\AccessResolver::class)
        ->fromMarketingToken($this->token)->identity;

    expect(app(PreferenceResolver::class)->allows($identity, 'account.security', 'mail'))->toBeTrue();
});

it('leaves a hand-tuned matrix alone when the cadence did not change', function () {
    chooseFrequency($this, $this->token, Frequency::IMMEDIATE);

    // Now switch one type's mail off by hand and resubmit the same cadence.
    $this->followingRedirects()->post(route('preference-center.token.update', $this->token), [
        'action' => 'save',
        'blocks' => ['frequency', 'types'],
        'frequency' => Frequency::IMMEDIATE,
        'types' => [
            'community.mention' => ['in_app' => '1'],
            'event.reminder' => ['in_app' => '1', 'mail' => '1'],
            'search.alert' => ['in_app' => '1', 'mail' => '1'],
            'account.security' => ['in_app' => '1', 'mail' => '1'],
        ],
    ])->assertOk();

    // The blunt control did not run, so the fine one won.
    expect(NotificationPreference::query()
        ->where('type', 'community.mention')->where('channel', 'mail')->value('enabled'))->toBeFalsy()
        ->and(NotificationPreference::query()
            ->where('type', 'event.reminder')->where('channel', 'mail')->value('enabled'))->toBeTruthy();
});

it('rejects a cadence nobody offers', function () {
    $this->followingRedirects()->post(route('preference-center.token.update', $this->token), [
        'action' => 'save',
        'blocks' => ['frequency'],
        // `mixed` is a state this page can read back and can never be asked for:
        // it means the matrix is not uniform, which is not something to choose.
        'frequency' => Frequency::MIXED,
    ])->assertOk()->assertSee(__('preference-center::public.refused_unknown'));

    expect(NotificationPreference::query()->count())->toBe(0);
});
