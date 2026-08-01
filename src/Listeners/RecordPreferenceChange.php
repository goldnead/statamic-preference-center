<?php

namespace Goldnead\PreferenceCenter\Listeners;

use Goldnead\Leadhub\Facades\LeadHub;
use Goldnead\PreferenceCenter\Events\PreferencesChanged;
use Illuminate\Support\Facades\Log;

/**
 * The audit trail.
 *
 * Marketing already records a consent proof for the list changes it makes, but
 * it records the only one it knows — `unsubscribe_token` — because until now
 * that was the only way onto its page. This page has three doors, and a record
 * that names the wrong one is worse than no record: it is a record that would
 * be relied upon.
 *
 * Two sinks, both safe when their target is absent:
 *
 *   - a structured log line, always;
 *   - the contact timeline, when LeadHub is installed and the address is known,
 *     because that is where a data-subject request will look.
 */
class RecordPreferenceChange
{
    public const LEADHUB = LeadHub::class;

    public function handle(PreferencesChanged $event): void
    {
        $payload = [
            'consent_proof' => $event->consentProof(),
            'brand_id' => $event->access->brandId,
            'identity' => $event->access->identity->pseudonymised()->toArray(),
            'changes' => $event->changes,
        ];

        Log::channel(config('preference-center.audit.log_channel'))
            ->info('preference-center.changed', $payload);

        $this->toLeadHub($event, $payload);
    }

    protected function toLeadHub(PreferencesChanged $event, array $payload): void
    {
        if (! config('preference-center.audit.leadhub', true)) {
            return;
        }

        if (! class_exists(self::LEADHUB) || $event->access->email === null) {
            return;
        }

        // `payload` is redacted by LeadHub on any key whose name contains
        // `token` or `secret`. `consent_proof` survives that; its *value* may be
        // the string `unsubscribe_token`, which is a word, not a token.
        (self::LEADHUB)::ingest([
            'email' => $event->access->email,
            'type' => 'preference_center.changed',
            'summary' => trans_choice(
                'preference-center::audit.summary',
                count($event->changes),
                ['count' => count($event->changes), 'proof' => $event->consentProof()],
            ),
            'source_type' => 'preference-center',
            'source_id' => $event->consentProof(),
            'dedupe_key' => 'preference-center:'.hash('sha256', json_encode([
                $event->access->brandId,
                $event->access->email,
                $event->changes,
                (int) floor(microtime(true) * 1000),
            ])),
            'payload' => $payload,
        ]);
    }
}
