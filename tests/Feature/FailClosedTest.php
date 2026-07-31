<?php

use Goldnead\PreferenceCenter\Tests\Fixtures\World;
use Goldnead\Suppression\Contracts\Gate;
use Goldnead\Suppression\Exceptions\SuppressionCheckFailed;

/**
 * What the page does when it cannot find out.
 *
 * `Gate::isSuppressed()` throws rather than returning false, and the contract
 * says so in words: a caller that catches it and carries on has converted a
 * fail-closed gate into a fail-open one. On a page that hands consent back,
 * carrying on would mean restoring subscriptions on the strength of a database
 * error.
 */
beforeEach(function () {
    $this->lists = World::lists();
    World::types();
    $this->token = World::subscriber('jane@example.com', ['newsletter'], $this->lists)->token;

    app()->bind(Gate::class, fn () => new class implements Gate
    {
        public function isSuppressed(string $email, ?int $brandId = null): bool
        {
            throw SuppressionCheckFailed::from(new RuntimeException('no connection'));
        }

        public function suppressedAmong(iterable $emails, ?int $brandId = null): array
        {
            throw SuppressionCheckFailed::from(new RuntimeException('no connection'));
        }
    });
});

it('treats an unqueryable gate as the closed answer, and says which it is', function () {
    $response = $this->get(route('preference-center.token', $this->token))->assertOk();

    // Not an error page, and not a shrug either. The page renders, everything
    // that could be blocked is treated as blocked, and the banner distinguishes
    // "blocked" from "we could not ask" — a visitor who is told the wrong one
    // of those goes looking for a block that does not exist.
    expect($response->getContent())->toContain('data-suppression="unavailable"')
        ->and($response->getContent())->toContain(__('preference-center::public.suppression_unavailable'));

    expect(renderedLists($response->getContent()))->toBe([
        'angebote' => 'blocked',
        'chorleitung' => 'blocked',
        'events' => 'blocked',
        'newsletter' => 'blocked',
        'saenger' => 'blocked',
    ]);

    expect(renderedCells($response->getContent())['search.alert.mail'])->toBe('locked-off');
});

it('refuses to switch anything on while it cannot ask', function () {
    $this->followingRedirects()
        ->post(route('preference-center.token.update', $this->token), [
            'action' => 'save', 'blocks' => ['types'],
            'types' => ['search.alert' => ['mail' => '1']],
        ])
        ->assertOk()
        ->assertSee(__('preference-center::public.refused_blocked'));

    expect(\Goldnead\Notifications\Models\NotificationPreference::query()
        ->where('channel', 'mail')->where('enabled', true)->count())->toBe(0);
});
