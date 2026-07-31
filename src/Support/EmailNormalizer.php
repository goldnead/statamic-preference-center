<?php

namespace Goldnead\PreferenceCenter\Support;

/**
 * The same normalisation the suppression table is keyed on.
 *
 * It defers to `goldnead/statamic-suppression` whenever that package is
 * installed, so the two can never drift apart: an address this page looked up
 * one way and the gate looked up another would produce a row that reads clear
 * on the page and blocked at the send, which is the exact failure marketing
 * 1.8.1 was released to end.
 *
 * The fallback is the same rule — trim, lowercase, nothing else. No dots
 * stripped, no plus-addressing collapsed: two addresses that a provider happens
 * to deliver to one mailbox are still two consents.
 */
final class EmailNormalizer
{
    public const SUPPRESSION = \Goldnead\Suppression\Support\EmailNormalizer::class;

    public static function normalize(?string $email): ?string
    {
        if (class_exists(self::SUPPRESSION)) {
            return (self::SUPPRESSION)::normalize($email);
        }

        if ($email === null) {
            return null;
        }

        $email = mb_strtolower(trim($email));

        return $email === '' ? null : $email;
    }
}
