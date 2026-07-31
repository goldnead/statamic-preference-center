<?php

namespace Goldnead\PreferenceCenter\Http\Controllers;

use Goldnead\PreferenceCenter\Http\SessionAccess;
use Goldnead\PreferenceCenter\MagicLink\LinkTokenizer;
use Goldnead\PreferenceCenter\MagicLink\MagicLinkRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Asking for a link, and following one.
 *
 * The response to a request is the same page with the same sentence whatever
 * happened, and it is held open to a floor so that the outcome cannot be read
 * off a stopwatch either. Both halves are needed: identical wording with a 12 ms
 * "no such person" and a 340 ms "mail sent" is an enumeration oracle with good
 * manners.
 */
class MagicLinkController extends Controller
{
    public function __construct(
        protected MagicLinkRequests $requests,
        protected LinkTokenizer $tokenizer,
        protected SessionAccess $session,
    ) {}

    public function form(Request $request)
    {
        abort_unless(config('preference-center.magic_link.enabled', true), 404);

        $this->applyBrand($request);

        return response()->view('preference-center::request', [
            'brand' => $this->requestedBrand($request),
        ]);
    }

    public function send(Request $request)
    {
        abort_unless(config('preference-center.magic_link.enabled', true), 404);

        $started = microtime(true);

        $this->applyBrand($request);

        // Not `email` validation on the request: a rejected field would answer
        // faster and differently than an accepted one, and the shape of an
        // address is the one thing about it we are willing to reveal. Malformed
        // input takes the same path and gets the same page.
        $outcome = $this->requests->request(
            is_string($request->input('email')) ? $request->input('email') : null,
            (string) $request->ip(),
            (int) app('brand-context')->currentId(),
        );

        $this->holdOpen($started);

        return redirect()->route('preference-center.request', array_filter(['pcBrand' => $this->requestedBrand($request)]))
            ->with('preference-center.status', __('preference-center::public.magic_link_sent'))
            ->with('preference-center.outcome', $outcome);
    }

    /**
     * Which brand this request page belongs to.
     *
     * Every other public entrance derives its brand from something the visitor
     * could not have chosen — a token, a signature. This one has nothing to
     * derive from: an address is not yet known to belong anywhere, and that is
     * the question being asked. So on a multi-brand host the page has to be
     * told, and `pcBrand` is how.
     *
     * It is safe to let a visitor name it, and it is not a hole for the same
     * reason the rest of this endpoint is not one: the answer is the same
     * sentence whichever brand is named, and whether the brand exists at all.
     * Naming a brand changes which audience is searched and which brand the
     * link opens — never whether anything is revealed.
     *
     * Like `SetBrandFromRouteValue`, an unknown handle aborts nothing. It finds
     * no brand, the current one stays, and the page behaves as it would have.
     */
    protected function requestedBrand(Request $request): ?string
    {
        $handle = $request->input('pcBrand');

        return is_string($handle) && $handle !== '' ? $handle : null;
    }

    protected function applyBrand(Request $request): void
    {
        $manager = app('brand-context');
        $handle = $this->requestedBrand($request);

        if ($handle === null || ! $manager->multiBrandEnabled()) {
            return;
        }

        $brand = \Goldnead\BrandContext\Models\Brand::query()->where('handle', $handle)->first();

        if ($brand !== null) {
            $manager->setCurrent($brand);
        }
    }

    public function open(Request $request, string $pcLink)
    {
        $payload = $this->tokenizer->open($pcLink);

        abort_unless($payload, 404);

        $this->session->open($request, $payload['email'], $payload['brand']);

        return redirect()->route('preference-center.show');
    }

    /**
     * Pad the response to the configured floor.
     *
     * A floor rather than a fixed duration: fixing it would make a slow mailer
     * visible as an overrun, and would also make the endpoint a convenient way
     * to hold a worker open.
     */
    protected function holdOpen(float $started): void
    {
        $floor = (int) config('preference-center.magic_link.min_response_ms', 350);
        $elapsedMs = (microtime(true) - $started) * 1000;

        if ($elapsedMs < $floor) {
            usleep((int) (($floor - $elapsedMs) * 1000));
        }
    }
}
