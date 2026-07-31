# Changelog

## 1.1.0 — 2026-07-31

Seven findings from an independent acceptance of 1.0.0, written by an agent that did not build it.
Two were blocking and the verdict was "not yet, for a production site". The three limits from L15
were checked twice there and held; they are checked again after these changes, on the hub, with the
new hidden fields stripped out of the submission.

### Fixed — the link request mailed nothing to anybody, on any brand

**What would have been possible: nothing.** That is the defect. The magic link is the only door for
somebody with no account and no old mail in their inbox, and on a multi-brand host it was shut for
every address, in every brand, for the whole life of 1.0.0.

The form carries two fields, `_token` and `email`. Nothing on it establishes a brand and no
middleware sets one for that route, so `BrandScope` failed closed and `known()` answered false for
every address ever typed into it. Measured on the QA hub against v1.0.0, with the same address in the
same second: **0 mails without `?pcBrand=`, 1 with it**. The README named `pcBrand`; the form did
not, and nothing in the response could have told anybody.

The property that makes this endpoint safe is what hid it. One sentence for every outcome — sent, no
such person, blocked, throttled — is deliberate, and it is also indistinguishable from a total
outage. A silence designed to reveal nothing revealed nothing about itself either.

**The decision, and why.** Three answers were available to "which brand, when nobody named one", and
two of them are wrong. A silent default brand is a bet: it searches one audience and gives everybody
else the same reassuring sentence, which is then untrue for every person who belongs to another
brand. A visible brand field publishes the brand list to anyone who loads the page, and asks somebody
who was mailed by one of several sister sites to remember which company that was. So the address
answers it: the lookup runs in every brand, and the mail carries one link per brand that has heard of
this address — normally exactly one, and then the mail is the mail it always was. The page still says
the same sentence either way; the only person who learns which brands know the address is whoever
reads that mailbox, and they already get mail from all of them. `pcBrand` still narrows the search
for a site that belongs to one brand, and still cannot widen it. The reasoning lives in
`MagicLinkRequests::brandsToSearch()`, where the code that acts on it is, and in the README.

The test that would have caught it now asserts the opposite of what its predecessor did: the old one
pinned "without the hint, the default brand is searched and this address is not in it — no mail",
which is the broken behaviour, written down and passed for a release.

### Fixed — a session id handed over before the click still opened the page

**What would have been possible:** read and change a stranger's preferences. Anybody who could plant
a session id in somebody else's browser — a shared machine, a forwarded URL carrying a session
parameter, a cookie written from a neighbouring subdomain, script injection anywhere on the domain —
kept access for the sixty minutes of the note as soon as that person followed a magic link. Their
address in the lede, every mailing list switchable, every notification switchable, and
"unsubscribe from everything" one button away. No account and no password anywhere in that chain.

`SessionAccess::open()` wrote the note into the session it was handed. It now regenerates the id
first and destroys the old record — the same move `Auth::login()` makes, for the same reason: from
the click onwards the session id *is* the credential. Measured on the QA hub against v1.0.0: the
cookie captured before the click, replayed from a separate browser context, answered **HTTP 200 with
the other person's address**. Afterwards it answers 404.

The test that pins it does not check that `regenerate()` was called. It captures the cookie, replays
it, and reads what comes back — and a second test follows the *new* cookie to the page, because a fix
that threw the note away with the old id would have passed the first one and been the worse bug.

### Fixed — the magic-link note outlived a login

The note outranks an authenticated session on purpose: whoever just followed a link is asking about
the address in that link, which need not be the address on the account they are still signed into.
That order is right for them and wrong for the next person, and nothing discarded the note, so on a
shared machine a colleague could sign in with their own account and be shown — and be able to change
— the first person's settings for an hour. A login now ends it.

`Login`, not `Authenticated`: the second fires on every request that resolves a user, including every
request by the person who was already signed in when they clicked their own link, and listening to it
would quietly reverse the ordering this package chose. There is a test for each direction.

### Fixed — the cadence overwrote, in silence, a box somebody had just cleared

A submission carrying `frequency=immediate` and one cleared checkbox ended with that checkbox back
on, no refusal, no notice, no trace. The README promised the opposite in as many words: "it cannot
flatten a matrix somebody just tuned by hand". Code and promise disagreed; the promise was right.

The cadence writes every optional mail-bearing cell, then the matrix writes the cells whose posted
value differs from the value the page rendered — which is exactly the set of boxes somebody clicked.
The stale-state worry the old skip came from is real and is answered by that comparison rather than by
a blanket skip: an untouched cell equals what was rendered, so the loop never reaches it and the
cadence's write stands. Both behaviours have a test now, and the README says what the code does.

### Fixed — the mail had no HTML part

`MagicLinkMail::build()` called `->text()` and nothing else: single-part `text/plain`, HTML length 0
in Mailpit, with a three-hundred-character signed URL as running text. Clients that do not linkify
wrap it; clients that do have to guess where it ends. Both parts are sent now, in one
`multipart/alternative`.

The two parts escape the URL in opposite directions and both are right: the plain-text body must not
escape the `&` before `signature` (that was the 1.0.0 regression — Blade's default made it `&amp;`
and Laravel answered 403 on every link), and the HTML body must, because an attribute value is an
HTML context and the client turns `&amp;` back into `&` before it opens anything. Each template says
so at the line that does it. The tests take the link out of each rendered body — decoding the href,
as a mail client would — and follow it, because "contains a URL" was true the whole time 1.0.0 was
broken.

### Fixed — the address limiter counted brand keys, not mailboxes

The per-address key was `hash(brand_id|address)`, which reads like tidy namespacing and gave every
brand its own budget of three an hour: **3×N mails into one inbox** on a host with N brands, with only
the origin limiter in the way. Measured on the QA hub: three requests for one address, differing only
in `pcBrand`, produced three separate counters at 1. The key is now the address and nothing else. A
limit that protects a key instead of a person protects nobody.

### Fixed — saving without changing anything answered with a wall of refusals

A blocked person pressed Save, touched nothing, and got "Nothing was changed" over ten red lines. No
forgery involved: a browser omits a `disabled` checkbox from the submission entirely, so a locked-on
cell arrived looking exactly like a cell somebody had just switched off, and the write path refused
each one — correctly, and at a control nobody had touched. A reader who is argued with by a page
about their own settings does not open a ticket; they press "spam".

Locked-on cells now carry a hidden field with the state the page displayed, so an untouched form
posts what it showed. It is not a way in: the writer re-reads every lock from the source, and a POST
that drops those fields is refused exactly as before — which is the case the third test in
`UntouchedSubmissionTest` posts. Blocked *list* rows deliberately get no such field:
`SubscriptionPreferences` leaves a blocked row alone in both directions and only objects to a
submission that asks for one to be switched on, so carrying the handle back would have manufactured
the refusal instead of preventing it.

### Notes

- Suite: **80 passed, 329 assertions** (63/229 before), SQLite in memory; `DB_DRIVER=mysql` runs the
  identical suite against a real server.
- Removal proof: each of the seven changes was taken back out in turn and its own tests re-run. Seven
  of seven turned red — 3 of 4 brand cases, 2 of 3 session cases, both login cases, the cadence case,
  3 of 4 mail cases, the limiter case, and both untouched-submission cases. One test in
  `StorageBoundaryTest` had pinned the old limiter key by reading the source; it now pins the new one,
  for the same reason it existed.
- The `MagicLinkMail` constructor now takes a list of links rather than one URL string, and
  `MagicLinkRequests::request()` takes `?int $brandId` where null means "no brand was named". Both are
  internal to the request path; a host that has not subclassed them is unaffected. `$mail->url()`
  replaces `$mail->url`.
- Verified on the QA hub, four brands, all three sources installed, in the area
  `preference-center-fixes` — the same measurement twice, once against v1.0.0 and once against this
  version: link request without a brand **0 → 1** mail (and an address known in two brands: **0 → 1
  mail carrying 2 links**); replayed session cookie **200 with a stranger's address → 404**; after a
  login in the same browser `data-proof` **magic_link with the other address → session with the
  account's own**; a cleared checkbox against `immediate` **enabled=1 → enabled=0**, silently → and
  now visibly off; the mail **text/plain, HTML length 0 → multipart/alternative, HTML length 2240,
  with the link taken out of the HTML part, entity-decoded and opened: HTTP 200**; six requests for
  one mailbox across three brands **6 → 3 mails**; blocked person presses Save **10 refusals → 0**,
  with the locks still on the page. Then the L15 re-check on the repaired build, with the new hidden
  fields stripped from the POST: cell states unchanged, zero notification channels switched on, the
  subscription untouched, the suppression row unreleased.
- The response-time floor was not touched. It is measured in that area and stated for what it is: at
  350 ms it sits far below the response times this host produces for either path, so it covers
  nothing here, and the acceptance was right that its coverage was never demonstrated. Raising it
  above the slowest send, or taking the send off the request, is the only thing that would — and
  neither is claimed.

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
