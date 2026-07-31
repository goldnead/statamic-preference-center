<?php

use Goldnead\PreferenceCenter\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * The state the page rendered for one mailing list, read out of the DOM.
 *
 * Reading the page rather than the database on purpose: what a visitor can act
 * on is what the page offered, and a check that only asks the model would pass
 * for a page that renders nothing at all.
 *
 * @return array<string,string>
 */
function renderedLists(string $html): array
{
    preg_match_all('/data-list="([^"]+)"\s+data-state="([^"]+)"/', $html, $matches, PREG_SET_ORDER);

    return collect($matches)->mapWithKeys(fn ($m) => [$m[1] => $m[2]])->all();
}

/**
 * The state of every cell of the notification matrix, keyed `type.channel`.
 *
 * `locked-on` and `on` are different states and the distinction is the point:
 * a required type is on and must stay on, which looks identical to an ordinary
 * switched-on cell in a screenshot and is not the same thing at all.
 *
 * @return array<string,string>
 */
function renderedCells(string $html): array
{
    preg_match_all('/data-cell="([^"]+)"\s+data-state="([^"]+)"/', $html, $matches, PREG_SET_ORDER);

    return collect($matches)->mapWithKeys(fn ($m) => [$m[1] => $m[2]])->all();
}

function renderedFrequency(string $html): ?string
{
    return preg_match('/data-block="frequency" data-frequency="([^"]*)"/', $html, $m) ? $m[1] : null;
}
