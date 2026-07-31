<?php

use Goldnead\PreferenceCenter\Tests\Fixtures\World;
use Goldnead\Suppression\Reasons;
use Goldnead\Suppression\SuppressionService;

/**
 * Two brands, one address, and the D1 split.
 *
 * The interesting case is not visibility — that a page shows one brand's lists
 * is the brand scope doing its job, and it is tested once below and then left
 * alone. The interesting case is the split itself: a hard bounce is a property
 * of the mailbox and crosses every brand, a complaint is a property of one
 * relationship and stays inside it. A page that got that backwards would either
 * keep mailing a dead address on behalf of the other brand, or treat one
 * brand's complaint as a company-wide ban.
 */
beforeEach(function () {
    $this->enableMultiBrand();

    $this->makeBrand('default', 'Default');
    $this->makeBrand('second', 'Second');

    World::types();

    $this->tokens = [];

    foreach (['default', 'second'] as $handle) {
        $this->inBrand($handle, function () use ($handle) {
            $lists = World::lists([$handle.'-newsletter']);
            $this->tokens[$handle] = World::subscriber('jane@example.com', [$handle.'-newsletter'], $lists)->token;
        });
    }

    app('brand-context')->forget();
});

it('shows one brand its own lists and nothing of the other', function () {
    $default = $this->get(route('preference-center.token', $this->tokens['default']))->assertOk();
    $second = $this->get(route('preference-center.token', $this->tokens['second']))->assertOk();

    expect(array_keys(renderedLists($default->getContent())))->toBe(['default-newsletter'])
        ->and(array_keys(renderedLists($second->getContent())))->toBe(['second-newsletter']);
});

it('lets a hard bounce close the address in every brand', function () {
    // `hard_bounce` is scoped `global` and stored with `brand_id = 0`. The
    // reason is not tidiness: the mailbox does not exist, and which brand is
    // asking cannot change that.
    $this->inBrand('default', fn () => app(SuppressionService::class)
        ->suppress('jane@example.com', Reasons::HARD_BOUNCE));

    $default = $this->get(route('preference-center.token', $this->tokens['default']))->assertOk();
    $second = $this->get(route('preference-center.token', $this->tokens['second']))->assertOk();

    expect(renderedLists($default->getContent()))->toBe(['default-newsletter' => 'blocked'])
        ->and(renderedLists($second->getContent()))->toBe(['second-newsletter' => 'blocked']);

    expect($default->getContent())->toContain('data-suppression="blocked"')
        ->and($second->getContent())->toContain('data-suppression="blocked"');
});

it('keeps a complaint inside the brand it was made in', function () {
    // `complaint` is scoped `brand`. Somebody who marked one brand's newsletter
    // as spam has said something about that relationship, not about the mailbox.
    $this->inBrand('default', fn () => app(SuppressionService::class)
        ->suppress('jane@example.com', Reasons::COMPLAINT));

    $default = $this->get(route('preference-center.token', $this->tokens['default']))->assertOk();
    $second = $this->get(route('preference-center.token', $this->tokens['second']))->assertOk();

    expect(renderedLists($default->getContent()))->toBe(['default-newsletter' => 'blocked'])
        ->and(renderedLists($second->getContent()))->toBe(['second-newsletter' => 'active']);

    expect($default->getContent())->toContain('data-suppression="blocked"')
        ->and($second->getContent())->not->toContain('data-suppression=');
});

it('refuses in the complaining brand what it allows in the other', function () {
    $this->inBrand('default', fn () => app(SuppressionService::class)
        ->suppress('jane@example.com', Reasons::COMPLAINT));

    // The same submission, the same address, the same page — two answers,
    // because the two tokens are in different brands.
    $this->followingRedirects()
        ->post(route('preference-center.token.update', $this->tokens['default']), [
            'action' => 'save', 'blocks' => ['types'],
            'types' => ['search.alert' => ['mail' => '1']],
        ])->assertOk()->assertSee(__('preference-center::public.refused_blocked'));

    $this->followingRedirects()
        ->post(route('preference-center.token.update', $this->tokens['second']), [
            'action' => 'save', 'blocks' => ['types'],
            'types' => ['search.alert' => ['mail' => '1']],
        ])->assertOk()->assertDontSee(__('preference-center::public.refused_blocked'));
});
