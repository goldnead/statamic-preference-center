<?php

namespace Goldnead\PreferenceCenter\Writing;

use Goldnead\PreferenceCenter\Data\Access;
use Goldnead\PreferenceCenter\Data\PreferenceView;
use Goldnead\PreferenceCenter\Data\TypeRow;
use Goldnead\PreferenceCenter\Events\PreferencesChanged;
use Goldnead\PreferenceCenter\Frequency;
use Goldnead\PreferenceCenter\PreferenceCenter;
use Goldnead\PreferenceCenter\Sources\NotificationsSource;

/**
 * The write path, and the only place a change is ever made.
 *
 * Everything this class refuses, the rendered page also refuses — but the two
 * refusals are independent, and that is the point. `disabled` is an instruction
 * the browser gives itself; a POST is what the server receives. Every lock in
 * the view is checked again here against state read fresh from the sources, so
 * a form with the attribute stripped out changes exactly nothing.
 *
 * The three limits, in the order they bite:
 *
 * 1. A `required()` type is not switchable, by anybody, through any door.
 * 2. A block is not lifted here. Not by a token, not by a signed link, not by a
 *    session. Bounce, complaint and opt-out survive all three.
 * 3. Whatever does change is announced with the proof that authorised it.
 */
class PreferenceWriter
{
    public function __construct(
        protected PreferenceCenter $center,
    ) {}

    /**
     * @param  array{lists?:array<int,string>, types?:array<string,array<string,mixed>>, frequency?:string}  $input
     */
    public function save(Access $access, array $input): WriteResult
    {
        $view = $this->center->view($access);
        $result = new WriteResult;

        // Order is the whole rule for the two that overlap: the cadence writes
        // every optional mail-bearing cell, then the matrix writes the cells
        // this submission actually changed. Last write wins, and the last write
        // is the specific one. See `saveTypes()`.
        $this->saveLists($access, $view, $result, $input['lists'] ?? null);
        $this->saveFrequency($access, $view, $result, $input['frequency'] ?? null);
        $this->saveTypes($access, $view, $result, $input['types'] ?? null);

        $this->announce($access, $result);

        return $result;
    }

    /** End every mailing list of this brand at once. Blocks are not touched. */
    public function unsubscribeFromEverything(Access $access): WriteResult
    {
        $result = new WriteResult;

        if (! $this->center->marketing->available()) {
            $result->refused('lists', 'source_absent');

            return $result;
        }

        $marketingCenter = $this->center->marketingCenter($access);

        if ($marketingCenter === null) {
            return $result;
        }

        foreach ($this->center->marketing->unsubscribeFromEverything($marketingCenter) as $handle) {
            $result->changed('lists', (string) $handle, false);
        }

        $this->announce($access, $result);

        return $result;
    }

    /**
     * Mailing lists.
     *
     * Handed straight to marketing's own `apply()`, which is where the rule
     * that a token may end consent and restore consent the person themselves
     * ended, but may never lift a block, is implemented and tested. Reproducing
     * that here would be a second implementation of it to keep in step.
     *
     * @param  array<int,string>|null  $wanted
     */
    protected function saveLists(Access $access, PreferenceView $view, WriteResult $result, ?array $wanted): void
    {
        if ($wanted === null) {
            return;
        }

        if (! $view->hasLists()) {
            $result->refused('lists', 'source_absent');

            return;
        }

        $marketingCenter = $this->center->marketingCenter($access);

        if ($marketingCenter === null) {
            return;
        }

        $outcome = $this->center->marketing->apply($marketingCenter, array_values(array_filter($wanted, 'is_string')));

        foreach ($outcome['subscribed'] as $handle) {
            $result->changed('lists', (string) $handle, true);
        }

        foreach ($outcome['unsubscribed'] as $handle) {
            $result->changed('lists', (string) $handle, false);
        }

        foreach ($outcome['refused'] as $handle) {
            $result->refused('lists.'.$handle, 'blocked');
        }

        if ($outcome['unknown'] !== []) {
            $result->refused('lists', 'unknown');
        }
    }

    /**
     * The cadence.
     *
     * Applied only when it differs from what is stored, because it is a blunt
     * control: it writes the mail and digest channel of every optional type at
     * once. Running it on an unchanged value would flatten a matrix the visitor
     * had just tuned by hand.
     */
    protected function saveFrequency(Access $access, PreferenceView $view, WriteResult $result, ?string $wanted): void
    {
        if ($wanted === null) {
            return;
        }

        if (! $view->hasFrequency()) {
            $result->refused('frequency', 'source_absent');

            return;
        }

        if (! Frequency::isKnown($wanted)) {
            $result->refused('frequency', 'unknown');

            return;
        }

        if ($wanted === $view->frequency) {
            return;
        }

        if (! $access->canStoreNotificationPreferences()) {
            $result->refused('frequency', NotificationsSource::LOCK_UNIDENTIFIED);

            return;
        }

        // A blocked address cannot be moved onto a cadence that means more mail.
        // It can always be moved to `never`, which means less.
        if ($view->suppression->blocksMail() && $wanted !== Frequency::NEVER) {
            $result->refused('frequency', NotificationsSource::LOCK_BLOCKED);

            return;
        }

        $state = Frequency::toChannelState($wanted);
        $optional = array_filter($view->types ?? [], fn (TypeRow $row) => ! $row->required);
        $channels = $view->mailChannels();

        foreach ($optional as $row) {
            foreach ($channels as $channel) {
                $enabled = (bool) $state[$channel];
                $frequency = $channel === 'digest' ? $state['digest_frequency'] : null;

                $this->center->notifications->set($access, $row->type, $channel, $enabled, $frequency);
            }
        }

        $result->changed('frequency', 'digest', $wanted);
    }

    /**
     * The type-by-channel matrix.
     *
     * Every cell that reaches a write here is a cell whose posted value differs
     * from the value that was rendered — the loop skips the rest — which makes
     * this list exactly the set of boxes somebody clicked. That is why it runs
     * after the cadence and is allowed to overwrite it: a submission carrying
     * `immediate` *and* one cleared checkbox is a person who chose both, and the
     * specific instruction is the one they will check afterwards.
     *
     * v1.0.0 read it the other way round. It treated the whole matrix as stale
     * once the cadence had run and skipped those cells, so a cleared box came
     * back on with no refusal, no notice and no trace — the one outcome a
     * preference page must never produce, and the opposite of what the README
     * promised. The stale-state worry it came from is real and is answered by
     * the comparison, not by a blanket skip: an untouched cell equals what was
     * rendered, so the loop never reaches it and the cadence's write stands.
     *
     * @param  array<string,array<string,mixed>>|null  $posted
     */
    protected function saveTypes(Access $access, PreferenceView $view, WriteResult $result, ?array $posted): void
    {
        if ($posted === null) {
            return;
        }

        if (! $view->hasTypes()) {
            $result->refused('types', 'source_absent');

            return;
        }

        $known = collect($view->types)->keyBy(fn (TypeRow $row) => $row->type);

        // Anything named that this installation does not have. A handle from
        // another brand's registry reads exactly like an invented one.
        foreach (array_keys($posted) as $type) {
            if (! $known->has((string) $type)) {
                $result->refused('types', 'unknown');
            }
        }

        foreach ($view->types as $row) {
            foreach ($view->channels as $channel) {
                $wanted = (bool) ($posted[$row->type][$channel] ?? false);

                if ($wanted === $row->isEnabled($channel)) {
                    continue;
                }

                if ($row->isLocked($channel)) {
                    $result->refused('types.'.$row->type.'.'.$channel, (string) $row->reason($channel));

                    continue;
                }

                $this->center->notifications->set($access, $row->type, $channel, $wanted);
                $result->changed('types', $row->type, $wanted, $channel);
            }
        }
    }

    protected function announce(Access $access, WriteResult $result): void
    {
        if (! $result->hasChanges()) {
            return;
        }

        event(new PreferencesChanged($access, $result->changes));
    }
}
