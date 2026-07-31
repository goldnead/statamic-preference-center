<?php

namespace Goldnead\PreferenceCenter\Data;

use Goldnead\IdentityContracts\Identity;
use Goldnead\PreferenceCenter\Proof;

/**
 * One visitor, however they got here.
 *
 * Three doors lead into this page — a token from a marketing mail, a signed
 * link this addon sent, and an authenticated session — and each of them knows
 * something different about who is on the other side. Everything past this
 * object sees only an `Identity`, the address, the brand and the proof.
 */
final class Access
{
    public function __construct(
        public readonly Identity $identity,
        public readonly string $proof,
        public readonly ?string $email,
        public readonly int $brandId,
        /** The marketing subscription token, when that is how we got here. */
        public readonly ?string $marketingToken = null,
    ) {
        Proof::assertKnown($proof);
    }

    /**
     * Whether product notification preferences may be stored for this visitor.
     *
     * `notification_preferences` is keyed on `user_id` and `contact_uuid`, both
     * of which are NULL for a person we could not place. Writing that row would
     * not fail — it would succeed, once, and then be shared by every other
     * unplaced visitor, because a hash of two NULLs is the same hash every
     * time. So an unidentified visitor reads defaults and writes nothing.
     */
    public function canStoreNotificationPreferences(): bool
    {
        return $this->identity->isIdentified();
    }
}
