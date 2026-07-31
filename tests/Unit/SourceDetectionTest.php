<?php

use Goldnead\PreferenceCenter\Sources\MarketingSource;
use Goldnead\PreferenceCenter\Sources\NotificationsSource;
use Goldnead\PreferenceCenter\Sources\SuppressionSource;

/**
 * How the page decides a source is there.
 *
 * By asking the class map, never by reading a composer manifest — the three
 * sources are `suggest`, and a host running a path repository, a fork or a
 * `replace` still has the classes while `composer show` would say something
 * else entirely.
 */
class AbsentMarketing extends MarketingSource
{
    protected function marker(): string
    {
        return 'Goldnead\\Marketing\\Services\\ThisClassDoesNotExist';
    }
}

it('reports a source absent when its classes are not on the class map', function () {
    // The only way to reach the `class_exists` branch in a suite that has all
    // three packages installed. Without a case like this, that one line is the
    // least-tested and most load-bearing line in the package.
    expect((new AbsentMarketing)->available())->toBeFalse()
        ->and((new AbsentMarketing)->installed())->toBeFalse()
        ->and((new AbsentMarketing)->enabled())->toBeTrue();
});

it('finds the suppression gate even though it is an interface', function () {
    // `class_exists()` answers false for an interface, and the marker for
    // suppression is its `Gate` contract. Getting that wrong does not throw —
    // it silently decides the package is absent and renders a page that reports
    // nothing as blocked, which is the one failure mode this family cannot have.
    expect(interface_exists(SuppressionSource::GATE))->toBeTrue()
        ->and(class_exists(SuppressionSource::GATE))->toBeFalse()
        ->and(app(SuppressionSource::class)->installed())->toBeTrue();
});

it('lets config switch a present source off but never switch an absent one on', function () {
    config()->set('preference-center.sources.marketing', false);
    expect(app(MarketingSource::class)->available())->toBeFalse();

    config()->set('preference-center.sources.marketing', 'auto');
    expect(app(MarketingSource::class)->available())->toBeTrue();

    config()->set('preference-center.sources.marketing', true);
    expect((new AbsentMarketing)->available())->toBeFalse();
});

it('knows its own config keys', function () {
    expect(app(MarketingSource::class)->key())->toBe('marketing')
        ->and(app(NotificationsSource::class)->key())->toBe('notifications')
        ->and(app(SuppressionSource::class)->key())->toBe('suppression');
});
