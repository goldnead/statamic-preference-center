<?php

use Goldnead\PreferenceCenter\Tests\Fixtures\World;
use Goldnead\StatamicAutomations\Services\SequenceOptOut;

/**
 * Wer auf der Seite nur eine Liste oder die Frequenz aendert, darf dabei keine
 * Serie verlassen. Das Formular schickt die angekreuzten Serien als
 * `sequences[]`; ein Controller, der das Feld nicht validiert, bekommt es nie zu
 * sehen und liest "Block gesendet, nichts angekreuzt" = alles abbestellen.
 *
 * Das Automations-Addon ist hier nicht installiert. Der Stub traegt den Namen
 * des Markers, den `SequencesSource` sucht, und merkt sich nur, was man ihm
 * sagt. Ohne gesetzte Zeilen bleibt der Block leer und damit unsichtbar, die
 * uebrigen Tests merken den Stub nicht.
 */
if (! class_exists(SequenceOptOut::class)) {
    eval(<<<'PHP'
    namespace Goldnead\StatamicAutomations\Services;

    class SequenceOptOut
    {
        public static array $rows = [];
        public static array $added = [];
        public static array $removed = [];

        public function sequencesFor(string $email)
        {
            return collect(array_map(fn ($r) => (object) $r, self::$rows));
        }

        public function add(string $uuid, string $email, string $source): void
        {
            self::$added[] = $uuid;
        }

        public function remove(string $uuid, string $email): void
        {
            self::$removed[] = $uuid;
        }
    }
    PHP);
}

beforeEach(function () {
    $stub = SequenceOptOut::class;

    if (! property_exists($stub, 'added')) {
        $this->markTestSkipped('Das echte Automations-Addon ist installiert; der Stub gilt nicht.');
    }

    $stub::$rows = [
        ['uuid' => 'seq-a', 'name' => 'Willkommen', 'opted_out' => false],
        ['uuid' => 'seq-b', 'name' => 'Einsingen', 'opted_out' => false],
    ];
    $stub::$added = [];
    $stub::$removed = [];

    $this->lists = World::lists();
    World::types();
    $this->token = World::subscriber('jane@example.com', ['newsletter'], $this->lists)->token;
});

afterEach(function () {
    SequenceOptOut::$rows = [];
});

it('laesst angekreuzte Serien laufen, wenn der Block gesendet wird', function () {
    $stub = SequenceOptOut::class;

    $this->followingRedirects()->post(route('preference-center.token.update', $this->token), [
        'action' => 'save',
        'blocks' => ['sequences'],
        'sequences' => ['seq-a', 'seq-b'],
    ])->assertOk();

    expect($stub::$added)->toBe([]);
});

it('beendet nur die Serie, deren Haken fehlt', function () {
    $stub = SequenceOptOut::class;

    $this->followingRedirects()->post(route('preference-center.token.update', $this->token), [
        'action' => 'save',
        'blocks' => ['sequences'],
        'sequences' => ['seq-a'],
    ])->assertOk();

    expect($stub::$added)->toBe(['seq-b']);
});

it('laesst Serien in Ruhe, wenn nur ein anderer Block gesendet wird', function () {
    $stub = SequenceOptOut::class;

    $this->followingRedirects()->post(route('preference-center.token.update', $this->token), [
        'action' => 'save',
        'blocks' => ['lists'],
        'lists' => ['newsletter'],
    ])->assertOk();

    expect($stub::$added)->toBe([])->and($stub::$removed)->toBe([]);
});

it('nimmt eine verlassene Serie wieder auf, wenn sie angekreuzt wird', function () {
    $stub = SequenceOptOut::class;
    $stub::$rows[1]['opted_out'] = true;

    $this->followingRedirects()->post(route('preference-center.token.update', $this->token), [
        'action' => 'save',
        'blocks' => ['sequences'],
        'sequences' => ['seq-a', 'seq-b'],
    ])->assertOk();

    expect($stub::$removed)->toBe(['seq-b'])->and($stub::$added)->toBe([]);
});

it('weist ein Serienfeld ab, das kein Array von Zeichenketten ist', function () {
    $stub = SequenceOptOut::class;

    $this->post(route('preference-center.token.update', $this->token), [
        'action' => 'save',
        'blocks' => ['sequences'],
        'sequences' => 'seq-a',
    ])->assertSessionHasErrors('sequences');

    expect($stub::$added)->toBe([]);
});
