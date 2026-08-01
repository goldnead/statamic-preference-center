<?php

namespace Goldnead\PreferenceCenter\MagicLink;

use Goldnead\PreferenceCenter\Support\EmailNormalizer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

/**
 * The magic link: a signed, expiring URL and nothing else.
 *
 * No table, and that is a decision rather than an omission. This package is a
 * view over three sources; a token table would make it the owner of a fourth,
 * with its own migration, its own index and its own pruning job, to hold a
 * fact Laravel can already carry in the URL and verify without a query.
 *
 * What the URL carries is encrypted, not merely signed. The signature already
 * makes it unforgeable; the encryption keeps the address out of access logs,
 * `Referer` headers and browser history, which a signed-but-plain link would
 * scatter it across.
 *
 * What this design does not give you is single use and revocation. The trade is
 * named in the README: a link is good for its lifetime, which is minutes, while
 * the marketing token that opens the same page is good forever, which is the
 * threat that actually governs. Shorten the lifetime rather than reaching for a
 * table.
 */
class LinkTokenizer
{
    /** @return string absolute, signed, expiring URL */
    public function issue(string $email, int $brandId): string
    {
        return URL::temporarySignedRoute(
            'preference-center.link',
            now()->addMinutes((int) config('preference-center.magic_link.ttl_minutes', 30)),
            ['pcLink' => $this->seal($email, $brandId)],
        );
    }

    /**
     * @return array{email:string, brand:int}|null null when the blob is not ours
     */
    public function open(string $blob): ?array
    {
        try {
            $decoded = json_decode(Crypt::decryptString($this->fromUrlSafe($blob)), true);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($decoded) || ! isset($decoded['e'], $decoded['b'])) {
            return null;
        }

        $email = EmailNormalizer::normalize((string) $decoded['e']);

        return $email === null ? null : ['email' => $email, 'brand' => (int) $decoded['b']];
    }

    protected function seal(string $email, int $brandId): string
    {
        return $this->toUrlSafe(Crypt::encryptString((string) json_encode([
            'e' => EmailNormalizer::normalize($email),
            'b' => $brandId,
            'i' => time(),
        ])));
    }

    protected function toUrlSafe(string $value): string
    {
        return rtrim(strtr($value, '+/', '-_'), '=');
    }

    protected function fromUrlSafe(string $value): string
    {
        $value = strtr($value, '-_', '+/');

        return str_pad($value, (int) (ceil(strlen($value) / 4) * 4), '=', STR_PAD_RIGHT);
    }
}
