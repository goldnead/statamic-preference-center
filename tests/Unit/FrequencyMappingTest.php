<?php

use Goldnead\PreferenceCenter\Frequency;

/**
 * The mapping between four words and the storage that holds two of them.
 *
 * Tested as arithmetic rather than through the page, because the property that
 * matters is total: every state maps somewhere, and every settable choice maps
 * back to itself. A page can only be trusted to show a choice the person made
 * if that is true before any HTTP is involved.
 */
it('writes a distinct state for each of the four', function () {
    $states = collect(Frequency::all())
        ->mapWithKeys(fn ($choice) => [$choice => json_encode(Frequency::toChannelState($choice))]);

    expect($states->unique()->count())->toBe(4);
});

it('reads every settable choice back as itself', function () {
    foreach (Frequency::all() as $choice) {
        $state = Frequency::toChannelState($choice);

        expect(Frequency::fromChannelState(
            $state['mail'],
            $state['digest'],
            $state['digest_frequency'] ?? 'weekly',
        ))->toBe($choice);
    }
});

it('reports a non-uniform matrix as mixed rather than rounding it to a word', function () {
    // Defaults alone produce this: one type whose default channels include
    // `mail` beside one whose defaults include `digest`. Reporting `weekly`
    // because a digest exists somewhere would put a caption on the page that
    // the page's own data contradicts.
    expect(Frequency::fromChannelState(true, true, 'weekly'))->toBe(Frequency::MIXED)
        ->and(Frequency::fromChannelState(true, true, 'daily'))->toBe(Frequency::MIXED);
});

it('does not offer mixed as a choice', function () {
    expect(Frequency::all())->not->toContain(Frequency::MIXED)
        ->and(Frequency::isKnown(Frequency::MIXED))->toBeFalse();
});

it('governs mail channels only, never the one that is not a mailbox', function () {
    // A block, and `never`, are both statements about mail. A page inside the
    // product is not a mailbox, and switching it off with a cadence control
    // would hide notifications the person never asked to hide.
    expect(Frequency::MAIL_CHANNELS)->toBe(['mail', 'digest'])
        ->and(Frequency::MAIL_CHANNELS)->not->toContain('in_app');
});
