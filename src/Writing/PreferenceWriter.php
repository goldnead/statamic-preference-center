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

        $this->saveLists($access, $view, $result, $input['lists'] ?? null);
        $cadenceApplied = $this->saveFrequency($access, $view, $result, $input['frequency'] ?? null);
        $this->saveTypes($access, $view, $result, $input['types'] ?? null, $cadenceApplied);

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
     *
     * @return bool  whether the cadence rewrote the mail-bearing channels
     */
    protected function saveFrequency(Access $access, PreferenceView $view, WriteResult $result, ?string $wanted): bool
    {
        if ($wanted === null) {
            return false;
        }

        if (! $view->hasFrequency()) {
            $result->refused('frequency', 'source_absent');

            return false;
        }

        if (! Frequency::isKnown($wanted)) {
            $result->refused('frequency', 'unknown');

            return false;
        }

        if ($wanted === $view->frequency) {
            return false;
        }

        if (! $access->canStoreNotificationPreferences()) {
            $result->refused('frequency', NotificationsSource::LOCK_UNIDENTIFIED);

            return false;
        }

        // A blocked address cannot be moved onto a cadence that means more mail.
        // It can always be moved to `never`, which means less.
        if ($view->suppression->blocksMail() && $wanted !== Frequency::NEVER) {
            $result->refused('frequency', NotificationsSource::LOCK_BLOCKED);

            return false;
        }

        $state = Frequency::toChannelState($wanted);
        $optional = array_filter($view->types ?? [], fn (TypeRow $row) => ! $row->required);
        $channels = $view->mailChannels();
        $touched = false;

        foreach ($optional as $row) {
            foreach ($channels as $channel) {
                $enabled = (bool) $state[$channel];
                $frequency = $channel === 'digest' ? $state['digest_frequency'] : null;

                $this->center->notifications->set($access, $row->type, $channel, $enabled, $frequency);
                $touched = true;
            }
        }

        $result->changed('frequency', 'digest', $wanted);

        return $touched;
    }

    /**
     * The type-by-channel matrix.
     *
     * @param  array<string,array<string,mixed>>|null  $posted
     * @param  bool  $cadenceApplied  when true the cadence has just written every
     *                                mail-bearing channel, so the checkbox state
     *                                that was rendered before it is stale and
     *                                those cells are left alone.
     */
    protected function saveTypes(Access $access, PreferenceView $view, WriteResult $result, ?array $posted, bool $cadenceApplied): void
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

                if ($cadenceApplied && in_array($channel, Frequency::MAIL_CHANNELS, true) && ! $row->required) {
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
