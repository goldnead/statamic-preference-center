<?php

namespace Goldnead\PreferenceCenter\Sources;

use Goldnead\PreferenceCenter\Data\SuppressionState;
use Goldnead\Suppression\Contracts\Gate;
use Goldnead\Suppression\Exceptions\SuppressionCheckFailed;

/**
 * The block state, from `goldnead/statamic-suppression`.
 *
 * This source answers one question — may this address be mailed — and it is
 * the only one of the three whose answer the visitor cannot change. A bounce,
 * a complaint or a manual opt-out survives every door into this page.
 */
class SuppressionSource extends Source
{
    public const GATE = Gate::class;

    public const CHECK_FAILED = SuppressionCheckFailed::class;

    public function key(): string
    {
        return 'suppression';
    }

    protected function marker(): string
    {
        return self::GATE;
    }

    /**
     * The gate resolves the brand itself. Passing one from here would let a
     * page decide which brand's blocks apply to it, and the brand this page
     * runs in was already established — by the token, by the signed link, or
     * by the session.
     */
    public function stateFor(?string $email): SuppressionState
    {
        if (! $this->available()) {
            return SuppressionState::notInstalled();
        }

        // An address we do not have cannot be cleared. The gate says the same
        // about an address that normalises to nothing; this says it earlier.
        if ($email === null || trim($email) === '') {
            return new SuppressionState(installed: true, blocked: true);
        }

        try {
            return new SuppressionState(
                installed: true,
                blocked: app(self::GATE)->isSuppressed($email),
            );
        } catch (\Throwable $e) {
            if (! is_a($e, self::CHECK_FAILED)) {
                throw $e;
            }

            // Fail closed. Not an error state shown to the visitor as a
            // shrug — the closed answer, with the page saying so.
            return new SuppressionState(installed: true, blocked: false, unavailable: true);
        }
    }
}
