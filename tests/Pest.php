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

/**
 * Every refusal the page is showing, wherever it puts them.
 *
 * Three places, because a refusal belongs at the control it refused: beside a
 * list row, under a type row, and in the loose list at the top for the ones
 * that belong to no single control.
 *
 * @return list<string>
 */
function renderedRefusals(string $html): array
{
    preg_match_all('#<span class="(?:error|why is-error)">(.*?)</span>#s', $html, $inline);
    preg_match_all('#<ul class="errors" role="alert">(.*?)</ul>#s', $html, $loose);
    preg_match_all('#<li>(.*?)</li>#s', implode('', $loose[1] ?? []), $items);

    return array_map('trim', array_merge($inline[1], $items[1]));
}

/**
 * What a browser would actually submit for the save form on this page.
 *
 * Written because the wall of refusals in L15's report was not produced by a
 * forged request. It was produced by pressing Save, and no test could see it:
 * every existing case builds its payload by hand, and a hand-built payload
 * contains the disabled boxes that a real browser silently drops. This reads
 * the rendered form the way a browser does — skipping `disabled` controls,
 * taking hidden fields, taking checkboxes and radios only when `checked` — so a
 * test can submit the page as displayed.
 *
 * @return array<string, mixed>  ready to hand to `post()`
 */
function submittedByBrowser(string $html): array
{
    preg_match_all('#<form\b[^>]*>(.*?)</form>#s', $html, $forms);

    $save = collect($forms[1])->first(fn ($body) => str_contains($body, 'name="action" value="save"')) ?? '';

    preg_match_all('#<input\b[^>]*>#s', $save, $inputs);

    $data = [];

    foreach ($inputs[0] as $tag) {
        if (str_contains($tag, 'disabled')) {
            continue;
        }

        $type = preg_match('/\btype="([^"]*)"/', $tag, $m) ? $m[1] : 'text';

        if (in_array($type, ['checkbox', 'radio'], true) && ! str_contains($tag, 'checked')) {
            continue;
        }

        if (! preg_match('/\bname="([^"]*)"/', $tag, $n)) {
            continue;
        }

        $value = preg_match('/\bvalue="([^"]*)"/', $tag, $v) ? html_entity_decode($v[1]) : 'on';

        // `lists[]`, `types[type][channel]`, `blocks[]`, `action`.
        if (preg_match('/^([^\[]+)\[\]$/', $n[1], $k)) {
            $data[$k[1]][] = $value;
        } elseif (preg_match('/^([^\[]+)\[([^\]]+)\]\[([^\]]+)\]$/', $n[1], $k)) {
            $data[$k[1]][$k[2]][$k[3]] = $value;
        } else {
            $data[$n[1]] = $value;
        }
    }

    return $data;
}
