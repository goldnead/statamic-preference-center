# Statamic Preference Center

**One page for everything a person receives.** The mailing lists they consented to, the product
notifications they can switch per kind and per route, how often those arrive, and the blocks that
override all three. It owns no table and invents no setting: every value on the page is read from
and written back to the package that already owns it.

Before this existed there were three truths in three places and no page that showed a person all of
them. The unsubscribe link was the only door, it worked on one list at a time, and it was a dead end
— it did not even reveal that the same person was still on four other lists of the same brand.

## Why it is its own package

It is a view, and a view over three packages belongs in none of them.

`statamic-marketing` owns consent to editorial mail. `statamic-notifications` owns the type × channel
matrix and the digest cadence. `statamic-suppression` owns the answer to whether an address may be
mailed at all. Putting the combined page in any one of them would make that one depend on the other
two, and the whole point of the arrangement is that each works alone.

All three are `suggest`, not `require`. A host with only notifications gets a working page. So does a
host with only marketing. That is not politeness — in the Hub this centre is set up for a brand whose
mix is not the mix on the site it was first designed for.

## Install

```bash
composer require goldnead/statamic-preference-center
```

The routes mount themselves under `!/preference-center`. Nothing else is required.

```bash
php artisan vendor:publish --tag=preference-center-config
php artisan vendor:publish --tag=preference-center-views
php artisan vendor:publish --tag=preference-center-translations
```

Point the digest footer at it:

```dotenv
NOTIFICATIONS_PREFERENCES_URL="https://example.com/!/preference-center"
```

## The three doors

Three ways in, and all three end at one `Identity` from `goldnead/statamic-identity-contracts` —
which exists precisely because a token holder has no account, a logged-in person has no subscription
token, and something has to carry a user id, a contact uuid, an address and an anonymous id side by
side.

| Door | URL | Identity | Proof |
|---|---|---|---|
| Token from a marketing mail | `/!/preference-center/t/{pcToken}` | contact, from the subscription's `contact_uuid`, else located by address | `unsubscribe_token` |
| Signed link, on request | `/!/preference-center/link/{pcLink}` → session | contact, located by the address sealed in the link | `magic_link` |
| Authenticated session | `/!/preference-center` | whatever `IdentityContext::resolve($user)` returns | `session` |

The brand comes from the door, never from the session: `SetBrandFromRouteValue` derives it from the
token, and a magic link carries its brand sealed inside it. A page that inherited the brand from
whatever the browser was last looking at would show one audience's lists to another's.

### The identity rule that is easy to get wrong in the friendly direction

`notification_preferences` is matched on `user_id` **and** `contact_uuid` with `=`, never OR. So the
identity this page writes must be the identity the sender reads. The session door therefore hands
over exactly what `IdentityContext::resolve()` produced, **unimproved** — helpfully attaching a
contact uuid the sender does not know about would write preferences into a row nothing ever reads.

And where no identity can be established at all, nothing is stored. Both keys NULL is not a row the
database rejects; it is one row, shared by every unplaceable visitor, because a hash of two NULLs is
the same hash every time. Those controls render locked and the write path refuses them twice.

## The three limits

Set in decision L15, and none of them is negotiable.

**1. `required()` types stay unswitchable.** For anybody, through any door. The lock cannot live in
the preference layer: `PreferenceResolver::allows()` returns `true` for a required type on every
channel before it reads anything stored — correct for a sender, useless for a form, because a cell
that is on and must stay on looks exactly like a cell that is on and may be turned off. So it lives
in the view **and** in the write path.

**2. A token does not lift a block.** Bounce, complaint and manual opt-out survive all three doors.
The two sources of a block — the contact's own opt-out and the suppression table — are read the way
`statamic-marketing` 1.8.1 reads them: batched, per row, and fail-closed. An unqueryable gate is not
a third opinion between blocked and clear; it *is* the closed answer, and the page says which of the
two it is rather than leaving a visitor hunting for a block that does not exist.

What a block does not stop is somebody withdrawing consent. Less mail is always allowed.

**3. Every change is recorded with the proof that authorised it.** `unsubscribe_token`, `magic_link`
or `session` — a `PreferencesChanged` event, a structured log line, and a LeadHub timeline entry
where LeadHub is installed. Marketing already recorded a proof for its own writes, but only ever the
one value it knew about; a record that names the wrong door is worse than no record, because it would
be relied on.

## The magic link

The one thing this package builds rather than borrows, and therefore the one whose security is its
own fault.

- **Signed and expiring**, via `URL::temporarySignedRoute`. Default 30 minutes, configurable.
- **Encrypted payload**, not merely signed. The signature already makes it unforgeable; the
  encryption keeps the address out of access logs, `Referer` headers and browser history.
- **It does not say whether an address exists.** Sent, no such person, blocked, throttled — the same
  page, byte for byte, and held open to a floor so the outcome cannot be read off a stopwatch either.
  Identical wording with a 12 ms "no such person" and a 340 ms "mail sent" is an enumeration oracle
  with good manners.
- **Throttled twice**, by address and by origin. One without the other is not a limit: per-address
  alone lets one client mail ten thousand different people, per-origin alone lets ten thousand
  clients mail one person. Both limiters count addresses nobody has heard of, or the limiter itself
  becomes the oracle.
- **A blocked address gets nothing.** "Here is your link to manage preferences" is still mail, and
  that mailbox is the one the provider told us to stop writing to.
- **An unknown address gets nothing.** Otherwise the endpoint mails a signed link to anything typed
  into it, which is an open relay with extra steps.
- **The address names the brand, not the visitor.** The form has two fields, `_token` and `email`,
  and no brand field. Every other entrance derives its brand from something the visitor could not
  choose; this one has nothing to derive from, because an address is not yet known to belong anywhere
  and that is the question being asked. So the lookup runs in every brand, and the mail carries one
  link per brand that has heard of this address — normally exactly one. Nothing is revealed by that:
  the page says the same sentence either way, and the only person who learns which brands know the
  address is whoever reads that mailbox.
- **`?pcBrand=<handle>` still narrows it**, carried into the POST as a hidden field, for a site that
  belongs to one brand of a multi-brand host. It cannot widen the search, and an unknown handle
  behaves exactly as no hint at all.
- **Two bodies, one message.** `text/plain` and `text/html` in a `multipart/alternative`. A signed
  link is around three hundred characters, and as running text alone it is wrapped by the clients
  that do not linkify and guessed at by the ones that do. The two parts escape the URL in opposite
  directions and both are right — see the note in each template, and the tests that follow the link
  out of each body rather than reading it.

### What a click counter does to it, and what this package does about that

A signed URL survives the post and does not always survive the courier. Providers that count clicks
rewrite every `href` in the HTML part onto their own redirector and append their own parameters when
they forward the reader — Brevo appends `_se`, the recipient address in base64. Laravel signs the
whole query string, so the URL that arrives is not the URL that was signed and the answer is **403**.
Measured on staging over a real Brevo send, on a link that passed the whole test suite:
`302 …sendibt3.com/tr/cl/… → 403 …/link/…?_se=…&expires=…&signature=…`. The plain-text link in the
same message, which Brevo leaves alone, worked. Nothing local can find this: no mail sink rewrites
links, which is exactly what makes a sink a sink.

Two answers, and a host wants both.

- **`delivery.mail_headers` — stop the rewriting.** Most providers take a per-message header that
  turns click tracking off for that one message, which is the right setting for a link that is
  transactional and has no click rate anybody wants. Whatever is in the map is added to the outgoing
  message verbatim, so this package presumes no provider: `X-Mailgun-Track-Clicks: no`,
  `X-PM-TrackLinks: None`, `X-Mailjet-TrackClick: 0`, `X-MSYS-API`, `X-SMTPAPI`, `X-MC-Track` — the
  table with the exact values is in the config file. Empty by default; an addon that guessed your
  provider and changed how it behaves would be the worse neighbour.

  **Brevo has no such header**, and that is not an oversight in this README. `X-Mailin-custom`,
  `X-Sib-Sandbox` and `X-SIB-API` are the documented ones and none of them touches tracking; the
  transactional API has no tracking option in its body either; Brevo has declined the request for
  years in its own community forum. On Brevo the ignore list below is not defence in depth, it is the
  only thing that works.

- **`delivery.ignored_query_parameters` — survive it.** These names are left out of the signature
  check, so a rewritten link still opens. Ignoring is giving away, so the list is short, every entry
  names the provider that adds it, and two names can never be on it: `expires` and `signature` are
  stripped out by `TrackingParameters` however the config is edited. What that leaves is bounded on
  purpose — the payload of a magic link is **encrypted into the path**, not the query, so there is
  nothing in the query for this route to protect except its lifetime, and its lifetime stays signed.
  A parameter nobody named is still refused, an edited payload is still refused, and a link past its
  time is still refused, each with a test that says so.

### What following one does to the session

The link is spent on arrival and leaves a short-lived note in the session, with its own expiry. Two
consequences that are not decoration:

- **The session id is regenerated, and the old one destroyed.** From the click onwards the session id
  is the credential, so anything that handed somebody an id they did not create — a shared machine, a
  forwarded URL, a neighbouring subdomain — would otherwise hand them the page.
- **A login ends the note.** It outranks an authenticated session on purpose, because whoever just
  followed a link is asking about the address in that link. But signing in says somebody else is at
  the keyboard now, and from there the session door takes over. `Login` only: the person who was
  already signed in when they clicked their own link keeps the note.

### What it deliberately does not have

No table, so no single use and no revocation. A link is good for its lifetime, which is minutes,
while the marketing token that opens the same page is good forever — that is the exposure that
actually governs, and it is the one L15 named and accepted. Shorten the lifetime rather than reaching
for a table.

## The cadence, over storage that holds two of its four words

`notification_preferences.frequency` accepts `daily` and `weekly`. There is no `immediate` and no
`never` in that addon, and inventing columns for them would make this package the owner of a data
model it is supposed to be a view over. So the other two are expressed as the channel state they
actually describe:

| Choice | mail | digest | `frequency` |
|---|---|---|---|
| Immediately | on | off | — |
| Daily | off | on | `daily` |
| Weekly | off | on | `weekly` |
| Never | off | off | — |

Four distinct stored states, so a choice reads back as the choice that was made. `never` leaves the
in-app channel alone: it is a cadence for mail, and a page inside the product is not a mailbox.
Required types are not touched by any of the four.

The matrix can also hold a state that is none of the four — one type mailed as it happens beside one
that is collected. Defaults alone produce it. The control then selects nothing and says the state is
mixed, rather than rounding it to the nearest word and putting a caption on the page that the page's
own data contradicts.

The cadence is a blunt control: it rewrites the mail and digest channel of every optional type. Two
things keep it from flattening a matrix somebody just tuned by hand. It runs only when the choice
actually changed — resubmitting the same word writes nothing. And when a submission carries a
cadence *and* a cleared checkbox, the checkbox wins: the cadence writes first, then every cell whose
posted value differs from the value the page rendered is written over the top of it. That set is
exactly the boxes somebody clicked, so an untouched cell keeps what the cadence gave it and a clicked
one keeps what the person asked for.

## What it stores

Nothing. There are no migrations, and that is the answer to two traps that took a sibling package
down twice: an index too wide for InnoDB, and a unique containing a nullable column, which
constrains nothing at all for the rows where it is null. Neither is visible on SQLite. A package that
owns no table cannot build either.

## Routes

| Name | Method | URI |
|---|---|---|
| `preference-center.show` | GET | `/!/preference-center` |
| `preference-center.update` | POST | `/!/preference-center` |
| `preference-center.token` | GET | `/!/preference-center/t/{pcToken}` |
| `preference-center.token.update` | POST | `/!/preference-center/t/{pcToken}` |
| `preference-center.request` | GET | `/!/preference-center/request` |
| `preference-center.request.send` | POST | `/!/preference-center/request` |
| `preference-center.link` | GET | `/!/preference-center/link/{pcLink}` |

Every parameter is prefixed `pc`. A `Route::bind()` is application-wide, not per package: a binding
another addon registers for `{token}` or `{link}` applies to every route with that name in every
installed package and resolves it against a repository that has never heard of these values. That is
exactly how `goldnead/statamic-leadhub` 1.8.0 shipped a delete button that did nothing.

The token routes are registered only where marketing is installed, because their brand middleware
names a marketing model.

## Reading the page in a test

The rendered page carries `data-*` attributes for every state it shows, so a check can quote what the
page is showing instead of a person squinting at a screenshot:

```
data-list="<handle>"       data-state="active|inactive|blocked"
data-cell="<type>.<chan>"  data-state="on|off|locked-on|locked-off"  data-reason="required|blocked|unidentified"
data-block="lists|types|frequency|none"
data-frequency="immediate|daily|weekly|never|mixed"
data-suppression="blocked|unavailable"
data-proof="unsubscribe_token|magic_link|session"
```

## Configuration

See `config/preference-center.php`. The values worth knowing:

| Key | Default | What it decides |
|---|---|---|
| `routes.prefix` | `!/preference-center` | Not `/preferences`: a host that owns that URL should not have to fight this addon for it |
| `sources.*` | `auto` | `false` turns a block off even where the package is installed. Nothing turns one on where the classes are missing |
| `magic_link.ttl_minutes` | `30` | Life of the signed URL |
| `magic_link.min_response_ms` | `350` | The floor under a link request. Raise it above your mailer's latency |
| `magic_link.throttle.*` | 3/hour per address, 10/hour per origin | |
| `magic_link.allow_unknown_addresses` | `false` | Leave it off unless nobody is known yet |
| `delivery.mail_headers` | `[]` | Per-message headers that tell your provider not to rewrite the links. Verified values per provider in the config file |
| `delivery.ignored_query_parameters` | `_se`, the five `utm_*`, `mc_cid`, `mc_eid`, `_hsenc`, `_hsmi`, `mkt_tok` | Left out of the signature check because a provider appends them. `expires` and `signature` cannot be added |
| `audit.log_channel` | default channel | |

## Tests

```bash
composer test
DB_DRIVER=mysql vendor/bin/pest      # the identical suite against a real server
```

## License

MIT.
