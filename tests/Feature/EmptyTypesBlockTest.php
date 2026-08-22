<?php

use Goldnead\PreferenceCenter\Data\Access;
use Goldnead\PreferenceCenter\Data\PreferenceView;
use Goldnead\PreferenceCenter\Data\SuppressionState;
use Goldnead\IdentityContracts\Identity;
use Goldnead\PreferenceCenter\Proof;

/**
 * Eine Ueberschrift ohne Inhalt ist schlimmer als keine.
 *
 * Seit notifications 1.7 die Arten nach Zustaendigkeit filtert, kommt eine
 * leere Liste regelmaessig vor: eine Newsletter-Adresse ohne Konto hat schlicht
 * keine Benachrichtigungen einzustellen. Der Block darf dann nicht mit dem
 * Satz "diese Installation kennt keine Benachrichtigungsarten" dastehen — der
 * waere doppelt falsch: sie kennt welche, und dem Leser hilft es nicht.
 */
function viewMit(?array $types): PreferenceView
{
    return new PreferenceView(
        access: new Access(
            identity: Identity::contact('c-1')->withEmail('jane@example.com'),
            proof: Proof::MAGIC_LINK,
            email: 'jane@example.com',
            brandId: 1,
        ),
        lists: [],
        types: $types,
        channels: [],
        frequency: null,
        suppression: new SuppressionState(installed: false, blocked: false),
    );
}

it('zeigt den Block nicht, wenn keine Art fuer diesen Menschen gilt', function (): void {
    expect(viewMit([])->hasTypes())->toBeFalse();
});

it('zeigt ihn auch nicht, wenn das Paket fehlt', function (): void {
    expect(viewMit(null)->hasTypes())->toBeFalse();
});

it('zeigt ihn, sobald es etwas einzustellen gibt', function (): void {
    expect(viewMit([['type' => 'community.mention']])->hasTypes())->toBeTrue();
});
