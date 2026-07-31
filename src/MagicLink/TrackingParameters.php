<?php

namespace Goldnead\PreferenceCenter\MagicLink;

/**
 * The query parameters a mail service provider adds to the link in transit, and
 * which of them the signature check is allowed to overlook.
 *
 * Why this exists. Brevo rewrites every `href` in the HTML part of a message
 * onto its own click counter, and when that counter forwards the reader it
 * appends `_se` — the recipient address in base64 — to the target URL. Laravel
 * signs the whole query string, so one appended parameter changes what is being
 * verified and `ValidateSignature` answers 403. Measured on staging: the button
 * in a real mail, opened from a real mailbox, produced
 * `302 …sendibt3.com/tr/cl/… → 403 …/link/…?_se=…&expires=…&signature=…`. The
 * plain-text link in the same message, which Brevo leaves alone, worked. This is
 * not specific to this package: it breaks every signed Laravel URL that is
 * mailed through a provider that counts clicks.
 *
 * What ignoring costs. Whatever is listed here is no longer covered by the
 * signature, so anybody may set it to anything. That is affordable for this one
 * route and not in general, for two reasons that both have to hold:
 *
 *   1. The payload is in the path, not in the query. `/link/{pcLink}` carries an
 *      encrypted blob; the address and the brand come out of `LinkTokenizer`,
 *      never out of a query parameter. The path stays inside the signed string.
 *   2. `expires` stays signed. It is the only query parameter this package puts
 *      meaning into, and the lifetime of the link is the whole revocation story
 *      — there is no token table to revoke against.
 *
 * So the second point is enforced here rather than trusted: `expires` and
 * `signature` are stripped out of whatever a host configures. A host who ignored
 * `expires` would still have expiry checked — `signatureHasNotExpired()` reads
 * it either way — but would have handed out the right to choose its value, which
 * turns a thirty-minute link into a permanent one. That is not a configuration
 * anybody should be able to make by editing a list of harmless-looking names.
 *
 * The list is a list, not a rule. An unknown parameter is still refused; only
 * the named ones are overlooked, and each name in the shipped default says which
 * provider adds it.
 */
final class TrackingParameters
{
    /**
     * Never ignorable, whatever the config says.
     *
     * `signature` is redundant — Laravel removes it from the string it verifies
     * on its own — and is listed anyway so that the guard reads as the complete
     * answer to "which parameters carry the guarantee".
     */
    public const RESERVED = ['signature', 'expires'];

    /**
     * The ignore list as the middleware wants it: a clean, unique, ordered list.
     *
     * Names are filtered to `[A-Za-z0-9_.-]` because this list is passed to the
     * router as a comma-separated middleware argument. A name with a comma in it
     * would not be one ignored parameter but two, one of them invented.
     *
     * @return list<string>
     */
    public static function ignored(): array
    {
        $configured = config('preference-center.delivery.ignored_query_parameters', []);

        $clean = [];

        foreach ((array) $configured as $parameter) {
            if (! is_string($parameter) && ! is_numeric($parameter)) {
                continue;
            }

            $parameter = trim((string) $parameter);

            if (preg_match('/^[A-Za-z0-9_.-]+$/', $parameter) !== 1) {
                continue;
            }

            if (in_array(strtolower($parameter), self::RESERVED, true)) {
                continue;
            }

            $clean[$parameter] = true;
        }

        return array_keys($clean);
    }
}
