<?php

namespace Goldnead\PreferenceCenter\Http\Controllers;

use Goldnead\BrandContext\Models\Brand;
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

        $named = $this->applyBrand($request);

        // Not `email` validation on the request: a rejected field would answer
        // faster and differently than an accepted one, and the shape of an
        // address is the one thing about it we are willing to reveal. Malformed
        // input takes the same path and gets the same page.
        //
        // The brand passed on is the one the visitor *named*, or null. Not
        // `currentId()`: that answers with the default brand whether anybody
        // chose it or not, and a service that cannot tell "this brand" from "no
        // brand was mentioned" has to guess. It guessed wrong for a whole
        // release — see `MagicLinkRequests::brandsToSearch()`.
        $outcome = $this->requests->request(
            is_string($request->input('email')) ? $request->input('email') : null,
            (string) $request->ip(),
            $named?->id,
        );

        $this->holdOpen($started);

        return redirect()->route('preference-center.request', array_filter(['pcBrand' => $this->requestedBrand($request)]))
            ->with('preference-center.status', __('preference-center::public.magic_link_sent'))
            ->with('preference-center.outcome', $outcome);
    }

    /**
     * Which brand this request page belongs to, if it was told.
     *
     * `pcBrand` is a hint and nothing more. A site that runs one brand of a
     * multi-brand host links to this page with its own handle, and then the
     * request searches that audience only. Left out — which is what the form
     * itself does, because it has no brand field — the address decides which
     * brands are searched. The reasoning for that lives at the one place that
     * acts on it, `MagicLinkRequests::brandsToSearch()`.
     *
     * It is safe to let a visitor name a brand for the same reason the rest of
     * this endpoint is safe: the answer is the same sentence whichever brand is
     * named, and whether the brand exists at all. Naming one changes which
     * audience is searched and which brand a link opens — never whether
     * anything is revealed.
     *
     * Like `SetBrandFromRouteValue`, an unknown handle aborts nothing. It finds
     * no brand, and the page behaves exactly as it would have without the hint.
     */
    protected function requestedBrand(Request $request): ?string
    {
        $handle = $request->input('pcBrand');

        return is_string($handle) && $handle !== '' ? $handle : null;
    }

    /** @return Brand|null  the brand that was named and found */
    protected function applyBrand(Request $request): ?Brand
    {
        $manager = app('brand-context');
        $handle = $this->requestedBrand($request);

        if ($handle === null || ! $manager->multiBrandEnabled()) {
            return null;
        }

        $brand = Brand::query()->where('handle', $handle)->first();

        if ($brand !== null) {
            $manager->setCurrent($brand);
        }

        return $brand;
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
