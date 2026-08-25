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

    /**
     * Could this address plausibly receive a mail?
     *
     * `filter_var($email, FILTER_VALIDATE_EMAIL)` is the obvious answer and the
     * wrong one: it predates RFC 6531 and rejects every non-ASCII character,
     * both in the local part and in the domain. So `bärbel.öztürk@beispiel.de`
     * came back invalid — an address this family happily stores as a contact,
     * subscribes to a list, and hands a working token link to.
     *
     * The consequence was not a visible error. The magic-link form answers the
     * same sentence to everyone on purpose, so that nobody can use it to find
     * out who is on a list. A rejected address is therefore indistinguishable
     * from an unknown one, and a person with an umlaut in their address could
     * never reach their own preferences — the one page the GDPR expects them to
     * reach. Silence made it invisible for as long as it existed.
     *
     * So: the domain is judged through its punycode form, and a Unicode local
     * part is judged on its own terms rather than declared malformed.
     */
    public static function looksDeliverable(?string $email): bool
    {
        if ($email === null || $email === '') {
            return false;
        }

        // 254 octets is the largest address an SMTP path can carry.
        if (strlen($email) > 254 || substr_count($email, '@') !== 1) {
            return false;
        }

        [$lokal, $domain] = explode('@', $email);

        if ($lokal === '' || $domain === '' || strlen($lokal) > 64) {
            return false;
        }

        if (($domain = self::punycode($domain)) === null) {
            return false;
        }

        // An ASCII local part has a well-tested judge already.
        if (! preg_match('/[^\x00-\x7F]/', $lokal)) {
            return filter_var($lokal.'@'.$domain, FILTER_VALIDATE_EMAIL) !== false;
        }

        // A Unicode one does not. It has to be well-formed UTF-8 and free of
        // the characters that would break the envelope or a header: spaces,
        // control characters, and the delimiters of the address syntax itself.
        return mb_check_encoding($lokal, 'UTF-8')
            && ! preg_match('/[\s@,;:<>"\[\]\\]|[\x00-\x1F\x7F]/u', $lokal)
            && filter_var('probe@'.$domain, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * The domain in the form a resolver understands.
     *
     * Without ext-intl there is no way to answer for an international domain,
     * and guessing is worse than declining: returning null keeps the caller
     * conservative instead of mailing into the dark.
     */
    private static function punycode(string $domain): ?string
    {
        if (! preg_match('/[^\x00-\x7F]/', $domain)) {
            return $domain;
        }

        if (! function_exists('idn_to_ascii')) {
            return null;
        }

        $ascii = idn_to_ascii($domain, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);

        return $ascii === false ? null : $ascii;
    }

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
