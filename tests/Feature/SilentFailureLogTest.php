<?php

use Goldnead\Leadhub\Facades\LeadHub;
use Goldnead\Marketing\Models\Subscription;
use Goldnead\PreferenceCenter\Http\Middleware\SetBrandFromLinkSession;
use Goldnead\PreferenceCenter\Http\SessionAccess;
use Goldnead\PreferenceCenter\MagicLink\MagicLinkRequests;
use Goldnead\PreferenceCenter\Sending\BrandMailer;
use Goldnead\PreferenceCenter\Sources\SuppressionSource;
use Goldnead\PreferenceCenter\Tests\Fixtures\World;
use Goldnead\Suppression\Contracts\Gate;
use Goldnead\Suppression\Exceptions\SuppressionCheckFailed;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Failures that used to vanish without a trace.
 *
 * Each of these is a path where the page still answers the way it should, and
 * the operator learns nothing: a 500 after a saved change, a refused send that
 * the log never names, a suppression check that failed quietly.
 */
beforeEach(function () {
    $this->lists = World::lists();
    World::types();
    $this->token = World::subscriber('jane@example.com', ['newsletter'], $this->lists)->token;

    $this->channel = Log::spy();
    Log::shouldReceive('channel')->andReturn($this->channel);
});

it('keeps a saved change a success when the audit trail cannot be written', function () {
    Exceptions::fake();

    // Only the audit write fails. Marketing writes to LeadHub too, and those
    // writes and the lookups go through the real manager.
    $real = LeadHub::getFacadeRoot();
    $manager = Mockery::mock($real)->makePartial();
    $manager->shouldReceive('ingest')
        ->withArgs(fn (array $data) => ($data['type'] ?? null) === 'preference_center.changed')
        ->andThrow(new RuntimeException('leadhub unreachable'));
    $manager->shouldReceive('ingest')->andReturnUsing(fn (array $data) => $real->ingest($data));
    LeadHub::swap($manager);

    $this->followingRedirects()
        ->post(route('preference-center.token.update', $this->token), [
            'action' => 'save', 'blocks' => ['lists'], 'lists' => ['chorleitung'],
        ])
        ->assertOk();

    // The change is stored: the audit failure must not undo it or hide it.
    expect(Subscription::query()->where('list_handle', 'chorleitung')->first()?->isSubscribed())->toBeTrue();

    Exceptions::assertReported(RuntimeException::class);

    // The log names the failure, and never the address.
    $this->channel->shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context = []) => $message === 'preference-center.audit.failed'
            && ! str_contains(json_encode($context), 'jane@example.com'))
        ->once();
});

it('logs a magic link that the mailer refused to send', function () {
    app()->instance(BrandMailer::class, new class extends BrandMailer
    {
        public function __construct() {}

        public function send(?int $brandId, string $to, ?string $toName, Mailable $mailable): bool
        {
            return false;
        }
    });

    expect(app(MagicLinkRequests::class)->request('jane@example.com', '203.0.113.1'))->toBe('misconfigured');

    $this->channel->shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context = []) => $message === 'preference-center.magic_link.not_sent'
            && ! str_contains(json_encode($context), 'jane@example.com'))
        ->once();
});

it('logs once when the suppression gate cannot be reached', function () {
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

    $state = app(SuppressionSource::class)->stateFor('jane@example.com');

    expect($state->unavailable)->toBeTrue();

    $this->channel->shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'suppression'))
        ->once();
});

it('logs when the brand remembered in the link session no longer exists', function () {
    $manager = Mockery::mock(app('brand-context'));
    $manager->shouldReceive('multiBrandEnabled')->andReturnTrue();
    $this->app->instance('brand-context', $manager);

    $request = Request::create('/preference-center/link', 'GET');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put(SessionAccess::BRAND, 999999);

    (new SetBrandFromLinkSession)
        ->handle($request, fn () => new Response);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'brand')
            && ($context['brand_id'] ?? null) === 999999)
        ->once();
});
