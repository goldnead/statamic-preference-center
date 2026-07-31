<?php

namespace Goldnead\PreferenceCenter\Events;

use Goldnead\PreferenceCenter\Data\Access;

/**
 * Something changed, and this is what authorised it.
 *
 * An event rather than a direct write so a host can put the record wherever it
 * keeps such things — an activity ledger, a warehouse, an SIEM — without this
 * package having to know about any of them. The shipped listener writes a log
 * line and, where LeadHub is installed, a contact-timeline entry.
 */
class PreferencesChanged
{
    /**
     * @param  list<array{block:string, target:string, channel:?string, to:mixed}>  $changes
     */
    public function __construct(
        public readonly Access $access,
        public readonly array $changes,
    ) {}

    public function consentProof(): string
    {
        return $this->access->proof;
    }
}
