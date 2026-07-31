<?php

use Goldnead\Marketing\Models\Subscription;
use Goldnead\Notifications\Models\NotificationPreference;
use Goldnead\PreferenceCenter\Tests\Fixtures\World;
use Goldnead\Suppression\Reasons;
use Goldnead\Suppression\SuppressionService;

/**
 * Pressing Save without touching anything.
 *
 * This is the one submission no test in v1.0.0 could make. Every case in the
 * suite builds its payload by hand, and a hand-built payload contains the boxes
 * a real browser drops: a `disabled` checkbox is not submitted at all, so a
 * locked-on cell arrives looking exactly like a cell somebody just switched
 * off. The write path refused each one, correctly and pointlessly, and a
 * blocked person who pressed Save without changing anything got "Nothing was
 * changed" over ten red lines — measured on the QA hub.
 *
 * So these tests submit what the rendered page actually offers. See
 * `submittedByBrowser()` in Pest.php.
 */
beforeEach(function () {
    $this->lists = World::lists();
    World::types();
    $this->subscription = World::subscriber('jane@example.com', ['newsletter', 'events'], $this->lists);
    $this->token = $this->subscription->token;
});

it('refuses nothing when a blocked person saves a page they did not touch', function () {
    app(SuppressionService::class)->suppress('jane@example.com', Reasons::HARD_BOUNCE);

    $page = $this->get(route('preference-center.token', $this->token))->assertOk();

    // The page is showing locks. That part was right and stays.
    expect(renderedCells($page->getContent())['account.security.mail'])->toBe('locked-on')
        ->and(renderedLists($page->getContent())['newsletter'])->toBe('blocked');

    $saved = $this->followingRedirects()
        ->post(route('preference-center.token.update', $this->token), submittedByBrowser($page->getContent()))
        ->assertOk();

    // Nothing changed, and nothing was refused. What did not change needs no
    // refusal: a rejection at a control nobody touched teaches the reader that
    // the page argues with them, and the next thing they press is "spam".
    expect(renderedRefusals($saved->getContent()))->toBe([])
        ->and($saved->getContent())->toContain(trans_choice('preference-center::public.saved', 0, ['count' => 0]));

    // And the state really is untouched, not merely quiet about it.
    expect(NotificationPreference::query()->count())->toBe(0)
        ->and(Subscription::query()->where('list_handle', 'newsletter')->value('status'))
        ->toBe($this->subscription->refresh()->status);
});

it('refuses nothing when an ordinary person saves a page they did not touch', function () {
    $page = $this->get(route('preference-center.token', $this->token))->assertOk();

    $saved = $this->followingRedirects()
        ->post(route('preference-center.token.update', $this->token), submittedByBrowser($page->getContent()))
        ->assertOk();

    expect(renderedRefusals($saved->getContent()))->toBe([])
        ->and($saved->getContent())->toContain(trans_choice('preference-center::public.saved', 0, ['count' => 0]));

    // The required type is still required and still on — the hidden field that
    // carries a locked cell's state through the submission says what the page
    // displayed, and the page displayed the lock.
    $again = $this->get(route('preference-center.token', $this->token))->assertOk();

    expect(renderedCells($again->getContent())['account.security.mail'])->toBe('locked-on')
        ->and(renderedLists($again->getContent()))->toBe(renderedLists($page->getContent()));
});

it('still refuses a submission that drops what the page carried', function () {
    // The three limits from L15, at the new seam. The fields added for the case
    // above are ordinary hidden inputs: anybody can delete them before posting,
    // which is precisely what a browser does to a disabled checkbox. That is not
    // a way in — the writer re-reads every lock from the source — and this is
    // the proof, not the argument.
    app(SuppressionService::class)->suppress('jane@example.com', Reasons::HARD_BOUNCE);

    $page = $this->get(route('preference-center.token', $this->token))->assertOk();
    $payload = submittedByBrowser($page->getContent());

    // Strip the required type's mail cell and the blocked list, and switch on
    // what the block is there to keep off.
    unset($payload['types']['account.security']['mail'], $payload['lists']);
    $payload['lists'] = ['newsletter', 'chorleitung', 'saenger', 'events', 'angebote'];
    $payload['types']['community.mention']['mail'] = '1';

    $refused = $this->followingRedirects()
        ->post(route('preference-center.token.update', $this->token), $payload)
        ->assertOk();

    expect($refused->getContent())->toContain(__('preference-center::public.refused_required'))
        ->and($refused->getContent())->toContain(__('preference-center::public.refused_blocked'));

    expect(Subscription::query()->where('list_handle', 'angebote')->exists())->toBeFalse()
        ->and(NotificationPreference::query()->whereIn('channel', ['mail', 'digest'])->where('enabled', true)->count())->toBe(0);

    $after = $this->get(route('preference-center.token', $this->token))->assertOk();

    expect(renderedCells($after->getContent())['account.security.mail'])->toBe('locked-on')
        ->and(renderedCells($after->getContent())['community.mention.mail'])->toBe('locked-on')
        ->and(renderedCells($after->getContent())['search.alert.mail'])->toBe('locked-off');
});
