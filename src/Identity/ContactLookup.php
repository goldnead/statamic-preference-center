<?php

namespace Goldnead\PreferenceCenter\Identity;

use Goldnead\IdentityContracts\Facades\IdentityContext;
use Goldnead\IdentityContracts\Identity;
use Goldnead\PreferenceCenter\Support\EmailNormalizer;

/**
 * Address to contact, where the installation can answer that.
 *
 * `statamic-identity-contracts` defines a `ContactLocator` for exactly this and
 * ships an inert default; nothing in this family binds a real one today. So
 * this asks the locator first — a host that bound one means it — and falls back
 * to LeadHub's repository when it is installed.
 *
 * This addon deliberately does not bind a `ContactLocator` of its own. That
 * contract is application-wide, and a preference page is not the right place to
 * change what `IdentityContext::resolve('someone@example.com')` means for every
 * other package in the process.
 */
class ContactLookup
{
    public const CONTACT_REPOSITORY = \Goldnead\Leadhub\Contracts\Repositories\ContactRepository::class;

    public const NULL_LOCATOR = \Goldnead\IdentityContracts\Resolvers\NullContactLocator::class;

    public function byEmail(?string $email): ?Identity
    {
        $normalized = EmailNormalizer::normalize($email);

        if ($normalized === null) {
            return null;
        }

        return $this->fromLocator($normalized) ?? $this->fromLeadHub($normalized);
    }

    protected function fromLocator(string $email): ?Identity
    {
        if (! interface_exists(\Goldnead\IdentityContracts\Contracts\ContactLocator::class)) {
            return null;
        }

        $locator = app(\Goldnead\IdentityContracts\Contracts\ContactLocator::class);

        // The shipped default answers null for everything. Calling it is
        // harmless; skipping it keeps the intent legible.
        if (is_a($locator, self::NULL_LOCATOR)) {
            return null;
        }

        return IdentityContext::locateContact($email);
    }

    protected function fromLeadHub(string $email): ?Identity
    {
        if (! interface_exists(self::CONTACT_REPOSITORY)) {
            return null;
        }

        if (! app()->bound(self::CONTACT_REPOSITORY)) {
            return null;
        }

        $contact = app(self::CONTACT_REPOSITORY)->findByEmailNormalized($email);

        if ($contact === null) {
            return null;
        }

        return Identity::contact(
            (string) $contact->uuid,
            (string) ($contact->email ?: $email),
            $contact->full_name ?: null,
        );
    }
}
