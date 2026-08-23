<?php

namespace Goldnead\PreferenceCenter\Identity;

use Goldnead\IdentityContracts\Facades\IdentityContext;
use Goldnead\IdentityContracts\Identity;
use Goldnead\PreferenceCenter\Data\Access;
use Goldnead\PreferenceCenter\Proof;
use Goldnead\PreferenceCenter\Sources\MarketingSource;
use Illuminate\Http\Request;
use Statamic\Facades\User as StatamicUser;

/**
 * Three doors, one `Identity`.
 *
 * This is the hard part of the package, and the reason it depends on
 * `statamic-identity-contracts` rather than inventing a visitor of its own: a
 * token holder has no account, a logged-in user has no subscription token, and
 * `Identity` is the one shape that carries a user id, a contact uuid, an
 * address and an anonymous id side by side.
 *
 * One rule governs the whole class, and it is easy to get wrong in the friendly
 * direction: **the identity written here must be the identity the sender
 * reads.** `notification_preferences` is matched on `user_id` AND
 * `contact_uuid` with `=`, never OR, so enriching a session identity with a
 * contact uuid the sender does not know about would write preferences into a
 * row nothing ever reads. The session door therefore hands over exactly what
 * `IdentityContext::resolve()` produced, unimproved.
 */
class AccessResolver
{
    public function __construct(
        protected MarketingSource $marketing,
        protected ContactLookup $contacts,
    ) {}

    /**
     * Door one: the token from a marketing mail.
     *
     * The brand is already current when this runs — `SetBrandFromRouteValue`
     * derived it from the token itself, before the controller, which is also
     * why an unknown token and another brand's token are indistinguishable
     * here: both find nothing.
     */
    public function fromMarketingToken(string $token): ?Access
    {
        if (! $this->marketing->available()) {
            return null;
        }

        $model = $this->marketing->subscriptionClass();
        $subscription = $model::query()->where('token', $token)->first();

        if ($subscription === null) {
            return null;
        }

        $email = (string) $subscription->email;

        $identity = $this->identityFor($email, $subscription);

        return new Access(
            identity: $identity,
            proof: Proof::UNSUBSCRIBE_TOKEN,
            email: $email,
            brandId: $this->brandId(),
            marketingToken: $token,
        );
    }

    /**
     * Door two: a signed link this addon mailed to the address on request.
     *
     * The brand was sealed into the link when it was issued, and the caller has
     * already made it current — a link must open the brand it was made in, not
     * whichever brand the session happens to be sitting in.
     */
    public function fromMagicLink(string $email): ?Access
    {
        return new Access(
            identity: $this->contacts->byEmail($email) ?? Identity::anonymous()->withEmail($email),
            proof: Proof::MAGIC_LINK,
            email: $email,
            brandId: $this->brandId(),
        );
    }

    /**
     * Door three: an authenticated session.
     *
     * Unimproved on purpose — see the class docblock.
     */
    public function fromSession(Request $request): ?Access
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $identity = IdentityContext::resolve($user);

        return new Access(
            identity: $identity,
            proof: Proof::SESSION,
            email: $identity->email ?: (is_string($user->email ?? null) ? $user->email : null),
            brandId: $this->brandId(),
        );
    }

    /**
     * Wer hinter diesem Token steckt — und zwar derselbe Mensch wie beim
     * Anmelden, nicht ein zweiter.
     *
     * Bis hierher wurde ein Token-Besuch immer als **Kontakt** aufgeloest,
     * auch bei jemandem mit Konto. Das hatte zwei Folgen, und beide sind
     * Fehler:
     *
     *  - `notification_preferences` haengt an (user_id, contact_uuid). Wer
     *    ueber den Mail-Link etwas einstellte, schrieb in eine andere Zeile
     *    als angemeldet — derselbe Mensch, zwei Einstellungssaetze, keiner
     *    sah den anderen.
     *  - Seit die Arten nach Zustaendigkeit gefiltert werden, verschwanden
     *    damit auch alle Einstellungen, die ein Konto voraussetzen. Adrian
     *    sah seine eigenen Community-Einstellungen nicht mehr, obwohl er ein
     *    Konto hat — gefunden im Nutzertest mit seinem Account.
     *
     * Der Token beweist die Verfuegung ueber das Postfach, und ein Konto mit
     * derselben Adresse gehoert derselben Person. Mehr darf dabei niemand:
     * `canStoreNotificationPreferences()` haengt an `isIdentified()`, und das
     * war ein Kontakt auch vorher schon.
     */
    protected function identityFor(string $email, object $subscription): Identity
    {
        $user = $email !== '' ? StatamicUser::findByEmail($email) : null;

        if ($user !== null) {
            return IdentityContext::resolve($user);
        }

        return $subscription->contact_uuid
            ? Identity::contact((string) $subscription->contact_uuid, $email, $this->nameOf($subscription))
            : ($this->contacts->byEmail($email) ?? Identity::anonymous()->withEmail($email));
    }

    protected function brandId(): int
    {
        return (int) app('brand-context')->currentId();
    }

    protected function nameOf(object $subscription): ?string
    {
        $name = trim(($subscription->first_name ?? '').' '.($subscription->last_name ?? ''));

        return $name === '' ? null : $name;
    }
}
