<?php

namespace Goldnead\PreferenceCenter\Http\Controllers;

use Goldnead\PreferenceCenter\Data\Access;
use Goldnead\PreferenceCenter\Http\SessionAccess;
use Goldnead\PreferenceCenter\Identity\AccessResolver;
use Goldnead\PreferenceCenter\PreferenceCenter;
use Goldnead\PreferenceCenter\Writing\PreferenceWriter;
use Goldnead\PreferenceCenter\Writing\WriteResult;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * The page itself, on two entrances that differ only in how the visitor is
 * recognised. Everything after `resolve()` is identical, on purpose: a token
 * holder sees and changes what a logged-in person sees and changes, minus what
 * nobody may change.
 */
class PreferenceCenterController extends Controller
{
    public function __construct(
        protected PreferenceCenter $center,
        protected AccessResolver $resolver,
        protected PreferenceWriter $writer,
        protected SessionAccess $session,
    ) {}

    /** The session and magic-link entrance. */
    public function show(Request $request)
    {
        $access = $this->sessionAccess($request);

        abort_unless($access, 404);

        return $this->render($access, 'preference-center.show');
    }

    public function update(Request $request)
    {
        $access = $this->sessionAccess($request);

        abort_unless($access, 404);

        return $this->apply($request, $access, route('preference-center.show'));
    }

    /** The marketing-token entrance. */
    public function showToken(string $pcToken)
    {
        $access = $this->resolver->fromMarketingToken($pcToken);

        abort_unless($access, 404);

        return $this->render($access, 'preference-center.token', [$pcToken]);
    }

    public function updateToken(Request $request, string $pcToken)
    {
        $access = $this->resolver->fromMarketingToken($pcToken);

        abort_unless($access, 404);

        return $this->apply($request, $access, route('preference-center.token', $pcToken));
    }

    /**
     * A magic-link session first, then an authenticated one.
     *
     * That order is not arbitrary: somebody who has just opened a link they
     * asked for is asking about the address in the link, which need not be the
     * address on the account they happen to still be logged into.
     */
    protected function sessionAccess(Request $request): ?Access
    {
        return $this->session->access($request) ?? $this->resolver->fromSession($request);
    }

    protected function render(Access $access, string $backRoute, array $backParameters = [])
    {
        return response()->view('preference-center::center', [
            'view' => $this->center->view($access),
            'back' => route($backRoute, $backParameters),
        ]);
    }

    protected function apply(Request $request, Access $access, string $back)
    {
        $data = $request->validate([
            'action' => ['nullable', Rule::in(['save', 'unsubscribe_all'])],
            'lists' => ['array'],
            'lists.*' => ['string', 'max:255'],
            'types' => ['array'],
            'types.*' => ['array'],
            'types.*.*' => ['string', 'max:8'],
            'frequency' => ['nullable', 'string', 'max:32'],
        ]);

        if (($data['action'] ?? 'save') === 'unsubscribe_all') {
            $result = $this->writer->unsubscribeFromEverything($access);

            return redirect($back)
                ->withErrors($this->errorBag($result))
                ->with('preference-center.status', trans_choice(
                    'preference-center::public.all_done',
                    $result->count(),
                    ['count' => $result->count()],
                ));
        }

        // Absent keys mean "nothing posted for this block", present-but-empty
        // means "everything off". `lists` and `types` are checkbox groups: a
        // browser omits an unchecked box entirely, so the form carries a marker
        // saying which blocks it rendered.
        $blocks = (array) $request->input('blocks', []);

        $result = $this->writer->save($access, array_filter([
            'lists' => in_array('lists', $blocks, true) ? ($data['lists'] ?? []) : null,
            'types' => in_array('types', $blocks, true) ? $this->normaliseTypes($data['types'] ?? []) : null,
            'frequency' => in_array('frequency', $blocks, true) ? ($data['frequency'] ?? null) : null,
        ], fn ($value) => $value !== null));

        return redirect($back)
            ->withErrors($this->errorBag($result))
            ->with('preference-center.status', trans_choice(
                'preference-center::public.saved',
                $result->count(),
                ['count' => $result->count()],
            ));
    }

    /** @return array<string, array<string,bool>> */
    protected function normaliseTypes(array $types): array
    {
        $out = [];

        foreach ($types as $type => $channels) {
            foreach ((array) $channels as $channel => $value) {
                $out[(string) $type][(string) $channel] = (bool) $value;
            }
        }

        return $out;
    }

    /** @return array<string,string> */
    protected function errorBag(WriteResult $result): array
    {
        $errors = [];

        foreach ($result->refusals as $refusal) {
            $errors[$refusal['key']] = __('preference-center::public.refused_'.$refusal['reason']);
        }

        return $errors;
    }
}
