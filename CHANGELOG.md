# Changelog

## 1.0.0 — 2026-07-31

### Added — one page over three packages, and none of them required

The mailing lists from `statamic-marketing`, the type × channel matrix and the digest cadence from
`statamic-notifications`, and the block state from `statamic-suppression`, on one page. Nothing here
is a new setting: every value is read from and written back to the package that owns it, and this
package ships no migration at all.

All three sources are `suggest`. Presence is decided by asking the class map, never a composer
manifest — a host on a path repository, a fork or a `replace` still has the classes. A block whose
package is absent is omitted from the page *and* refused by the write path, independently, so a form
rendered before a switch was thrown does not sneak past.

One detail that would have been silent rather than loud: the marker for suppression is its `Gate`
**interface**, and `class_exists()` answers false for an interface. Getting that wrong does not
throw — it decides the package is absent and renders a page that reports nothing as blocked, which is
the one failure mode this family cannot have. There is a test that pins it.

### Added — three doors, one `Identity`, and the rule that keeps the sender agreeing with the page

A token from a marketing mail, a signed link on request, and an authenticated session all end at one
`Identity` from `statamic-identity-contracts`. The brand comes from the door in every case: derived
from the token by `SetBrandFromRouteValue`, or sealed into the magic link and restored from the
session on the way back.

The rule that is easy to get wrong in the friendly direction: `notification_preferences` is matched
on `user_id` AND `contact_uuid` with `=`, never OR. So the session door hands over exactly what
`IdentityContext::resolve()` produced, unimproved. Attaching a contact uuid the sender does not know
about would write preferences into a row nothing ever reads — a setting the person made and the
product will never honour.

Where no identity can be established, nothing is written. Both keys NULL is not a duplicate the
database rejects; it is *one* row, shared by every unplaceable visitor, because
`UniquenessKey::of([null, null, …])` is the same hash every time and `PreferenceResolver::set()` does
not check. The controls render locked and the write is refused twice — once by the view's lock, once
by the source itself, because the second is what stops a queued job or a future controller.

### Added — the three limits from L15, each enforced twice

`required()` types stay unswitchable, a block is not lifted by any door, and every change is recorded
with the proof that authorised it.

The first one cannot live in the preference layer: `PreferenceResolver::allows()` returns `true` for
a required type on every channel before it reads anything stored. That is right for a sender and
useless for a form, because a cell that is on and must stay on looks exactly like a cell that is on
and may be turned off. So the lock is computed in the view and checked again in the writer against
state read fresh — `disabled` is an instruction the browser gives itself, and a POST is what the
server receives. Posting a required type off, a blocked list on and a blocked channel on in one
submission changes nothing and produces three refusals at the three controls they belong to.

The block is read the way marketing 1.8.1 reads it, by handing list writes back to
`SubscriptionPreferences::apply()` rather than reimplementing the rule beside it. Fail-closed
throughout: an unqueryable gate blocks everything and the banner distinguishes "blocked" from "we
could not ask", because a visitor told the wrong one of those goes looking for a block that does not
exist.

Marketing records `consent_proof` for its own writes but only ever the one value it knew about,
`unsubscribe_token`, because until now that was the only way onto its page. This page has three
doors, so it announces its own `PreferencesChanged`, writes a structured log line, and adds a LeadHub
timeline entry with the identity pseudonymised. Nothing is recorded when nothing changed.

### Added — a magic link that is not an enumeration oracle or a mail amplifier

Nobody without an account and without an old mail could reach their settings at all. The link closes
that, and it is the only piece of this package whose security is its own fault rather than borrowed.

Signed and expiring via `URL::temporarySignedRoute`, with the address encrypted inside the URL rather
than merely signed — the signature makes it unforgeable, the encryption keeps the address out of
access logs and `Referer` headers.

Sent, no such person, blocked and throttled all return the same page **byte for byte**, and the
response is held open to a configurable floor. Both halves are needed: identical wording with a 12 ms
"no such person" and a 340 ms "mail sent" is an enumeration oracle with good manners. Two limiters,
by address and by origin, because one without the other is not a limit — per-address alone lets one
client mail ten thousand different people, per-origin alone lets ten thousand clients mail one
person. Both count addresses nobody has heard of, or the limiter becomes the oracle the rest of the
endpoint avoids. A blocked address is never written to, and neither is an address this installation
has no relationship with.

No token table, and that is a decision. It costs single use and revocation; it buys not owning a
fourth data model with its own migration, its own index and its own pruning job. The link lives
minutes while the marketing token that opens the same page lives forever, so the shorter one is not
where the exposure is. Named in the README rather than left to be discovered.

### Added — four cadences over storage that holds two of the words

`notification_preferences.frequency` accepts `daily` and `weekly` and nothing else. `immediate` and
`never` are stored as the channel state they describe — mail on/digest off, and both off — so all
four are distinct in storage and every one reads back as itself. `never` leaves the in-app channel
alone: it is a cadence for mail, and a page inside the product is not a mailbox.

The matrix can also hold a state that is none of the four, and defaults alone produce it: one type
mailed as it happens beside one that is collected. The control then selects nothing and says the
state is mixed. Rounding it to `weekly` because a digest exists somewhere would have put a caption on
the page that the page's own data contradicts.

The cadence rewrites every optional type's mail and digest channel, so it runs only when it actually
changed — otherwise it would flatten a matrix somebody had just tuned by hand.

### Added — cross-brand, and the half of it that is not about visibility

That one brand's page shows one brand's lists is the brand scope doing its job, and it is tested once.
The case worth the test is the D1 split: a hard bounce is scoped `global` and closes the address in
every brand, a complaint is scoped `brand` and stays inside the one it was made in. The same address,
the same submission, two different answers depending on which brand's token opened the page.

### Added — the brand a request page belongs to, because there is nothing to derive it from

Every other public entrance takes its brand from something the visitor could not have chosen: a
token, a signature. The link-request page has nothing to take it from — an address is not yet known
to belong anywhere, and that is the question being asked. On a multi-brand host it would therefore
have searched the default brand and found nobody, which is how it behaved on the QA hub before
`pcBrand` existed.

Letting a visitor name the brand is safe here for the same reason the rest of the endpoint is safe:
the answer is the same sentence whichever brand is named, and whether the brand exists at all. It
changes which audience is searched and which brand the link opens, never what is revealed. An unknown
handle aborts nothing, exactly as `SetBrandFromRouteValue` does not.

### Fixed — the link in the mail was a 403 before anyone could click it

Found by opening it, not by reading it. The plain-text mail rendered `{{ $url }}`, so Blade escaped
the `&` between `expires` and `signature` into `&amp;`. The link looks perfect to a reader, and
Laravel rejects it as unsigned — a 403 on the one link the person asked for, on the only door built
for people who have no other way in.

The regression test does not assert that the body contains a URL. It extracts the URL from the
rendered body and follows it, because "contains a URL" was true the whole time it was broken.

### Notes

- Suite: **63 passed, 229 assertions** (SQLite in memory; `DB_DRIVER=mysql` runs the identical suite
  against a real server).
- Removal proof: each of **19** guarantees was deleted in turn and the suite re-run. 19 of 19 turned
  red — including the write-path guard that the view's lock would otherwise have hidden, which was
  green on the first pass and got its own direct test as a result.
- Verified on the QA hub against brand `familystack`, with all three sources installed: the three
  blocks on one token page, a blocked list row, a `required` type rendered `locked-on`, a browser
  tamper (four `disabled` attributes stripped, the blocked boxes ticked, the required type unticked)
  leaving both tables character-for-character unchanged, and the magic link followed from the request
  form through Mailpit to the opened page. Two defects were found there and are fixed above; neither
  was visible from inside the suite as it stood.
- Route parameters are `pcToken` and `pcLink`. A `Route::bind()` is application-wide, and
  `{token}`/`{link}` are exactly what a sibling in this family would reach for. There is a test that
  registers those bindings and checks this addon still answers.
- No migrations, so no index width to compute and no nullable unique to get wrong. The NULL trap that
  does apply here is in a sibling's table, and the guard against it is a refusal to write rather than
  a constraint to rely on.
