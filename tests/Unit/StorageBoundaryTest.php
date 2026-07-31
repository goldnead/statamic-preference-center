<?php

use Goldnead\Notifications\Support\UniquenessKey;

/**
 * What this package stores, and the two traps it stays out of by storing it.
 *
 * Index width and the NULL trap are the pair that took `statamic-notifications`
 * down twice: a unique across four `varchar(255)` columns is 3212 bytes under
 * utf8mb4 and InnoDB refuses it at 3072, and a unique containing a nullable
 * column constrains nothing at all for the rows where it is null. Neither is
 * visible on SQLite.
 *
 * This package's answer to both is to own no table.
 */
it('ships no migrations, so it adds no index and no unique to trip over', function () {
    expect(glob(__DIR__.'/../../database/migrations/*.php') ?: [])->toBe([])
        ->and(is_dir(__DIR__.'/../../database'))->toBeFalse();
});

it('leaves the NULL trap unreachable rather than merely unlikely', function () {
    // Here is the trap, in one line. Two people this installation cannot place
    // — no user id, no contact uuid — produce the *same* uniqueness key for the
    // same type and channel, because `-;` is what NULL hashes to.
    $anonymousA = UniquenessKey::of([null, null, 'community.mention', 'mail']);
    $anonymousB = UniquenessKey::of([null, null, 'community.mention', 'mail']);

    expect($anonymousA)->toBe($anonymousB);

    // So the row is not a duplicate the database would reject. It is one row,
    // shared, and the second visitor's choice overwrites the first's. That is
    // why `Access::canStoreNotificationPreferences()` refuses to write at all
    // rather than writing carefully — the constraint cannot save us here, and
    // `PreferenceResolver::set()` does not check.
    expect(UniquenessKey::of(['4711', null, 'community.mention', 'mail']))->not->toBe($anonymousA);
});

it('keeps every key it hands the database to a fixed 64 characters', function () {
    // 64 characters is 256 bytes under utf8mb4, against InnoDB's 3072. Whatever
    // else changes, the width of what this page writes does not depend on how
    // long a type handle or an address happens to be.
    $long = str_repeat('a', 400);

    expect(UniquenessKey::of([$long, $long, $long, $long]))->toHaveLength(64);
});

it('bounds its own cache keys the same way', function () {
    // The magic-link limiter is keyed on a hash of the address, not the address.
    // A cache key that grows with its input is the same class of mistake as an
    // index that does — and an address is attacker-controlled input.
    $service = new ReflectionClass(\Goldnead\PreferenceCenter\MagicLink\MagicLinkRequests::class);
    $source = file_get_contents($service->getFileName());

    expect($source)->toContain("hash('sha256', \$brandId.'|'.\$email)")
        ->and($source)->toContain("hash('sha256', \$origin)");
});

it('refuses the write itself, not only the control that offers it', function () {
    // The lock in the view stops the form. This stops everything else: a host
    // calling the source directly, a future controller, a queued job. The two
    // are independent on purpose — the view's answer is about what to draw, and
    // this one is about what may reach `notification_preferences`.
    $anonymous = new \Goldnead\PreferenceCenter\Data\Access(
        identity: \Goldnead\IdentityContracts\Identity::anonymous()->withEmail('nobody@example.com'),
        proof: \Goldnead\PreferenceCenter\Proof::MAGIC_LINK,
        email: 'nobody@example.com',
        brandId: 1,
    );

    expect($anonymous->canStoreNotificationPreferences())->toBeFalse();

    expect(fn () => app(\Goldnead\PreferenceCenter\Sources\NotificationsSource::class)
        ->set($anonymous, 'community.mention', 'mail', true))
        ->toThrow(LogicException::class);

    expect(\Goldnead\Notifications\Models\NotificationPreference::query()->count())->toBe(0);
});
