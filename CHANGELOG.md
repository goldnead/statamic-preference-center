# Changelog

## 1.7.0 — 2026-08-25

### Fixed

- **A person with an umlaut in their address could never reach their own preferences.** The magic
  link form validated with `filter_var($email, FILTER_VALIDATE_EMAIL)`, which predates RFC 6531 and
  rejects every non-ASCII character. `bärbel.öztürk@beispiel.de` — a contact in the CRM, a
  subscriber on two lists, and someone whose token link works perfectly — was answered as unknown.

  The failure had no symptom, and that is the point: this form answers the same neutral sentence to
  everyone on purpose, so that nobody can use it to find out who is on a list. A rejected address is
  therefore indistinguishable from an unknown one. The page the GDPR expects a person to be able to
  reach was closed to them, silently, for as long as the check existed.

  `EmailNormalizer::looksDeliverable()` now judges the domain through its punycode form and a
  Unicode local part on its own terms, while still refusing what is genuinely undeliverable.

## 1.6.3 — 2026-08-23

### Fixed — die Matrix zeichnete Kästchen für Kanäle, die es nicht gibt

Die Spalten kommen aus der globalen Kanalliste, die Zeilen aus den Arten. Seit
`goldnead/statamic-notifications` 1.7 kann eine Art sagen, welche Kanäle sie
überhaupt führt — die Zeile fragte aber nicht danach und zeichnete für jede
Spalte ein Kästchen.

Auf der Produktion sichtbar an `crm.task_assigned`: die Art führt seit
leadhub 2.5 keinen Digest mehr, in der Tabelle stand die Digest-Spalte
trotzdem. Anklickbar, gespeichert, und beim Versand ignoriert.

Genau die Sorte Bedienelement, die diese Reihe abschaffen sollte: ein
Kästchen, das aussieht wie eine Wahl und keine ist.

Eine Art ohne diesen Kanal bekommt jetzt eine leere Zelle. Wichtig dabei:
„nicht vorhanden" und „aus" bleiben verschieden — ein ausgeschalteter Kanal
ist eine Wahl, ein fehlender ist keine.

## 1.6.2 — 2026-08-23

### Fixed — ein Mensch, eine Einstellung, egal durch welche Tür

Ein Besuch über den Mail-Link wurde immer als **Kontakt** aufgelöst, auch bei
jemandem, der ein Konto hat. Das hatte zwei Folgen, und beide sind Fehler:

- `notification_preferences` hängt an `(user_id, contact_uuid)`. Wer über den
  Mail-Link etwas einstellte, schrieb in eine andere Zeile als angemeldet —
  derselbe Mensch, zwei Einstellungssätze, keiner sah den anderen. Das war
  schon vor dieser Reihe so und fiel nur niemandem auf.
- Seit 1.6.1 die Arten nach Zuständigkeit filtert, verschwanden über den
  Mail-Link zusätzlich alle Einstellungen, die ein Konto voraussetzen.

Findet sich zu der Adresse ein Konto, wird der Besuch jetzt als dieses Konto
aufgelöst — dieselbe Kennung wie beim Anmelden. Mehr darf dabei niemand: der
Token beweist ohnehin die Verfügung über das Postfach, und
`canStoreNotificationPreferences()` hing nie am Kontotyp, sondern nur daran,
ob die Person überhaupt einzuordnen ist.

Gefunden in einem Nutzertest mit einem echten Konto — kein Test hätte das
treffen können, weil keiner je einen Nutzer anlegte.

### Fixed — die Testsuite hing von früheren Läufen ab

`statamic.users.repository` steht in der Suite auf `file`, und der Treiber
schreibt echte YAML-Dateien. Die Datenbank wird zwischen Tests zurückgesetzt,
diese Dateien nicht. Solange kein Test Nutzer anlegte, blieb das folgenlos;
mit dem ersten, der es tut, fielen prompt drei fremde Tests um. `setUp()` räumt
das Verzeichnis jetzt aus.

## 1.6.1 — 2026-08-22

### Fixed — der leere Benachrichtigungs-Block stand mit einem falschen Satz da

Seit `goldnead/statamic-notifications` 1.7 die Arten nach Zuständigkeit
filtert, kommt eine leere Liste regelmäßig vor: eine Newsletter-Adresse ohne
Konto hat schlicht keine Benachrichtigungen einzustellen. Der Block wurde
trotzdem gezeichnet — mit der Zeile „Diese Installation kennt keine
Benachrichtigungsarten", die dann doppelt falsch war: die Installation kennt
sehr wohl welche, und dem Leser hilft der Satz ohnehin nicht.

`hasTypes()` verlangt jetzt Zeilen, nicht nur ein vorhandenes Array. Für den
Menschen davor sind „keine registriert" und „keine für dich" dasselbe: es gibt
nichts einzustellen, und eine Überschrift ohne Inhalt ist schlimmer als keine.

## 1.6.0 — 2026-08-22

### Added — laufende Serien verlassen

Der vierte Block, und der einzige mit einer Zwischenstufe. Listen sind An/Aus
für ein ganzes Thema, die Frequenz gilt für alles — eine Serie ist ein
einzelner Strang, den man verlassen kann, ohne den Rest aufzugeben. Genau das
ist der Fall, den jemand meint, der eine Willkommensstrecke nicht zu Ende lesen
will.

Gezeigt werden laufende Serien (ein Lauf wartet noch auf seinen nächsten
Schritt) **und bereits verlassene**. Ohne die verlassenen wäre der Block nach
dem Ausstieg leer und der Weg zurück nirgends zu finden.

Wie die drei anderen Quellen über `class_exists` geschützt: ohne
`goldnead/statamic-automations` gibt es keine Serien und damit keinen Block.
Abschaltbar über `preference-center.sources.sequences`.

## 1.5.2 — 2026-08-13

### Added — a test holds the informal address, and the way back is documented

1.5.1 changed the German wording and left the net for it in the host that had reported the
problem. That is the wrong place for a package's own guarantee: a refactor here, or a fresh
installation, would not notice the register switching back.

`tests/Feature/GermanFormOfAddressTest.php` checks every German string in `de/` for formal
address forms. It matches the shapes the formal register actually uses rather than snapshotting
sentences, so rewording stays free and switching back to „Sie" does not — and it distinguishes
the address „Sie" from the plural pronoun „sie", which appears in these files legitimately
(„Redaktionelle E-Mails. Sie beruhen auf deiner Einwilligung") and has to stay. Checked against
the 1.5.0 files: three of the four assertions fail there.

An installation that prefers the formal register publishes the translations and edits them:
`php artisan vendor:publish --tag=preference-center-translations`. Wording is not part of this
package's public contract (see the README), which is why 1.5.1 was a patch release.

## 1.5.1 — 2026-08-13

### Changed — the German strings now use „du", like the rest of this family

This package was the only one of the goldnead addons whose German addressed the reader formally
(„Ihr Link zu den E-Mail-Einstellungen", „kopieren Sie diese Adresse"). `statamic-marketing` and
`statamic-notifications` have always used „du". On a host running all three, the same person got a
„du" confirmation mail and a „Sie" preferences link — and the preferences page they landed on
switched back again.

Both German files are converted: `resources/lang/de/mail.php` (the magic-link mail) and
`resources/lang/de/public.php` (the page that link opens). **Wording only.** Sentence structure,
order, placeholders and keys are untouched; only the form of address changed. The one remaining
„Sie" in `lists_hint` is the pronoun for „E-Mails", not an address.

English is unchanged. Nothing in `src/` or in the views changed, so no behaviour did.

## 1.5.0 — 2026-08-12
### Changed

- **The five sender-identity classes moved to `goldnead/statamic-brand-context` 1.8.0**, which is
  now required at `^1.8`. They were four byte-identical copies with four namespaces — this package,
  marketing, notifications and automations each grew their own on 12.08.2026 — and copies drift: by
  the evening the marketing one had stopped refusing a transport without an address. Which address a
  brand sends under is a property of the brand, so the rule lives with the brand.

  Behaviour is unchanged here, down to the log lines. `Goldnead\PreferenceCenter\Contracts\SenderIdentityResolver`
  and `Sending\BrandMailer` stay as this package's own extension points. `Sending\SenderIdentity`
  and `Sending\SaidRecently` are gone from this namespace; use the `Goldnead\BrandContext\Sending\`
  versions.

## 1.4.0 — 2026-08-12

### Fixed — the magic link went out under the host's identity, not the brand's

`MagicLinkRequests` ended in `Mail::to($email)->send(new MagicLinkMail($links))`: one mail carrying
every brand's link, through the process-wide default mailer, with the process-wide `mail.from`. A
mail that speaks for several brands has to pick one From, and on a host where each brand's domain
is verified in its own relay account (Scaleway TEM, Postmark, SES) there is no correct pick — the
provider refuses the address or substitutes its own, so the person who asked brand A about their
settings hears from brand B.

**One mail per brand now, each as that brand.** `Contracts\SenderIdentityResolver` answers "which
mailer, which From, which locale for brand N" out of `brands.settings.mail`; `Sending\BrandMailer`
is the single door the mail leaves through and puts the answer on the message
(`Mailable::from()`, `Mail::mailer($name)`) rather than into config — Laravel burns `mail.from`
into a mailer instance on first resolution and caches it for the life of the process, so a scoped
`Config::set` would escape its own `finally`.

**In the ordinary case nothing changes.** One brand knows the address, so one link, so one mail —
the same mail as before, now over that brand's own transport. Only the rare address several brands
know is split, and the split is what makes each part honest. The cost, stated plainly: such an
address receives one mail per brand for one request, so the per-address ceiling becomes
`max × brands-that-know-it`. Bounded by the host's brand list, not by anything a caller supplies,
and every one of those mails comes from a sender the recipient already has a relationship with.

### Changed — the mail names its brand whenever the link has one

Previously only when several links shared one mail. With the links now in separate mails, that was
the sentence that said which is which. A single-brand install has no brand on the link and the
label stays absent, exactly as before.

### Changed — a brand that declares a broken mail identity sends nothing

A brand that declares `settings.mail` without `from_address`, or names a `mailer` that
`config/mail.php` does not define, sends nothing and says so at error level, throttled per brand.
Delivering under the host-wide From instead would be delivering under another brand's identity,
quietly. The other brands that know the address still get their mail; `MagicLinkRequests::request()`
returns the new outcome `misconfigured` only when none of them got out. Like every other outcome it
is for logs and tests — the page says the same sentence whatever happened, which is the whole point
of the endpoint.

**A single-brand install is unchanged, and that is covered by a test rather than by intent.** So is
a multi-brand install whose brands carry no `settings.mail`: both resolve to the config identity,
including the package's own `preference-center.magic_link.from`. A host that keeps sender
identities somewhere else rebinds `SenderIdentityResolver` in its own provider.
## 1.3.1 — 2026-08-09

### Fixed — the sibling constraint excluded the new majors

`goldnead/statamic-leadhub` (`^1.10`) and `goldnead/statamic-marketing` (`^1.8.1`) were pinned to the 1.x line. LeadHub 2.0.0 and Marketing 2.0.0 carry no code change over 1.12.2
and 1.13.0 — that major is the licence switch alone. A site running both this package and an
updated sibling could not resolve its dependencies at all. The constraints now accept both
lines.

## 1.3.0 — 2026-08-01
### The preference page is now owned here, and marketing asks for it

Two packages were serving near-identical preference pages. That is decided: this one owns the page,
`goldnead/statamic-marketing` keeps only a one-click unsubscribe path that works whether or not this
package is installed, and it routes every preference link through a resolver that prefers this page.

**New — a discovery interface, semver-bound from this release.**
`PreferenceCenter::urlForToken(string $token): ?string` and `PreferenceCenter::requestUrl(): ?string`,
plus the route-name constants `ROUTE_TOKEN`, `ROUTE_SHOW` and `ROUTE_REQUEST`. `null` means "this
package cannot serve that link here, use your own path" — returned when the routes are not mounted,
when marketing is absent or switched off, or when the token is empty. Pinned by
`tests/Feature/DiscoveryContractTest.php`, including the negative case that matters most: a sibling
must probe `class_exists()` on the class, never `method_exists()` on the facade, which answers `false`
through `__callStatic` and took every LeadHub action node down in
`goldnead/statamic-automations` v1.0.3.

**New — [UPGRADE.md](UPGRADE.md)**, with the cutover for a host that had marketing first: what happens
to the links already sitting in people's inboxes, and the route-cache clear that is not optional here.

### Major changes

- **`statamic/cms` moved from `suggest` to `require` (`^6.0`).** It was never optional: this package
  hard requires `goldnead/statamic-brand-context`, which hard requires `statamic/cms ^6.0`. There has
  never been an installation without Statamic in it. `"type": "statamic-addon"` was the honest half of
  that pair; the missing constraint was the dishonest one, and the Marketplace listing had no
  compatibility metadata as a result.
- **`laravel/framework` narrowed to `^12.40|^13.0`.** `^11.0` was unsatisfiable in practice — Statamic
  6 requires `^12.40 || ^13.0` — and the whole Laravel 11 line is withdrawn over security advisories.
  Nobody can have been running this on Laravel 11.
- **`orchestra/testbench` narrowed to `^10.0|^11.0` and `pestphp/pest` to `^3.0|^4.0`**, for the same
  reason: the lower halves of those ranges pair with Laravel versions this package can no longer
  install. Dev-only, so no consumer is affected.

### Also

- `extra.statamic` now carries `slug`, `url`, `developer` and `developer-url`, so the manifest slug is
  no longer `null` and the CP addon card has a developer link.
- Pint, Larastan (level 5 with a baseline) and a `.gitattributes` that keeps tests and CI out of the
  installed package.
- CI grew the axes the constraints were always claiming: PHP × Laravel × `prefer-lowest|prefer-stable`,
  a MySQL leg that finally uses the second connection in `tests/TestCase.php`, Pint, PHPStan and
  addon-lint. The `SIBLING_REPOS_TOKEN || github.token` fallback is gone — it could never read a
  private sibling and turned a missing secret into a Composer 404 that read like a network blip.
- `composer.lock` is no longer tracked. A library's lock constrains nothing for consumers and published
  the full private dependency graph into a repo that is going public.
- `SECURITY.md`, a Requirements section, a support policy, and an install section that finally admits
  the root-level `repositories` entries a consumer needs while the siblings are off Packagist.

## 1.2.0 — 2026-07-31

One finding from a real end-to-end run on staging: a magic link requested there, mailed by Brevo, read
in a real mailbox, and the button clicked the way a recipient clicks it. **HTTP 403.** Not a defect in
the signature and not a defect in the mail — a defect in the delivery chain, which is the one place
this suite could not reach.

### Fixed — a click counter appended one parameter and the link stopped working

**What was possible: nothing, again, for whoever clicks the button.** The redirect chain, as captured:

```
302  https://fbjicab.r.bh.d.sendibt3.com/tr/cl/…                       Brevo's click counter
403  https://staging.adriangoldner.com/!/preference-center/link/…
     ?_se=aW5mbyt0ZXN0QGFkcmlhbmdvbGRuZXIuY29t&expires=…&signature=…
```

Brevo rewrites every `href` in the HTML part onto its own counter, and when the counter forwards the
reader it appends `_se` — the recipient address in base64, inserted **in front of** `expires` and
`signature`. Laravel signs the whole query string. One extra parameter changes what is verified, and
`ValidateSignature` answers 403 before this package sees the request. The plain-text link in the same
message, which Brevo leaves alone, worked throughout. This is not specific to this addon: **a
provider that counts clicks breaks every signed Laravel URL it is asked to deliver.**

**Why nothing local could have found it, and why it took a real send.** Every test in this package
follows the link out of the rendered body — that check was written in 1.1.0 precisely because
"contains a URL" had been true while the URL was broken. It follows the link the *sink* stored. A
mail sink stores what it was handed; that is what makes it a sink. Mailpit does not rewrite `href`s,
`Mail::fake()` does not build a MIME message at all, and neither of them owns a click counter.
Nothing short of a message through a real provider, opened from a real mailbox, produces the URL that
actually arrives. The gap was not in the assertions. It was in the last hop, and the only instrument
that reaches it is a send.

**Two answers, and a host wants both.**

*Stop the rewriting.* `delivery.mail_headers` is a map of headers added verbatim to the outgoing
message, so the package presumes no provider and a host can name its own: `X-Mailgun-Track-Clicks:
no`, `X-PM-TrackLinks: None`, `X-Mailjet-TrackClick: 0`, `X-MSYS-API`, `X-SMTPAPI`, `X-MC-Track` —
the table with each vendor's exact value, checked against each vendor's own documentation, is in the
config file. A magic link is transactional; nobody wants a click rate for it, and a link nobody
touched cannot be broken. Empty by default: an addon that guessed the provider and changed how it
behaves would be the worse neighbour.

**Brevo has no such header, and that was checked rather than assumed.** The brief for this change
said one existed and to verify it in Brevo's documentation instead of guessing. It does not.
`X-Mailin-custom`, `X-Sib-Sandbox` and `X-SIB-API` are the documented ones and none of them touches
tracking; the transactional API has no tracking option in its body either; Brevo has declined the
request for years in its own community forum, and Anymail's provider matrix records the same in one
sentence: "Brevo does not provide a way to control open or click tracking for individual messages."
The account-level setting Brevo does offer anonymises the tracking, it does not stop the rewriting.
So on Brevo the second answer is not defence in depth. It is the only thing that works.

*Survive the rewriting.* `delivery.ignored_query_parameters` names the parameters left out of the
signature check. Ignoring is giving away, so what is given away is stated rather than glossed:

- The **payload is in the path**, not the query. `/link/{pcLink}` carries an encrypted blob and the
  address and brand come out of `LinkTokenizer`; the path stays inside the signed string. The query
  of this URL carries `expires` and `signature` and nothing else — there is no third thing in it to
  protect.
- **`expires` stays signed**, and cannot be unsigned by editing a config file. `TrackingParameters`
  strips `expires` and `signature` out of whatever a host lists. A host who ignored `expires` would
  still have expiry *checked* — `signatureHasNotExpired()` reads it either way — and would have
  handed out the right to *choose its value*, turning a thirty-minute link into a permanent one. This
  package has no token table, so the lifetime is the whole revocation story.
- The list is **a list, not a rule**. Each entry names the provider that adds it: `_se` (Brevo, the
  one that was measured), the five `utm_*` (Brevo's Google-Analytics tagging and the same switch in
  Mailchimp, Mailjet, Postmark), `mc_cid`/`mc_eid` (Mailchimp), `_hsenc`/`_hsmi` (HubSpot),
  `mkt_tok` (Marketo). `gclid` and `fbclid` are deliberately absent: an ad network is not on the path
  from a mail to this route, and a list that grows by association is how one ends up ignoring the
  wrong thing.

### Notes

- Suite: **93 passed, 372 assertions** (80/329 before), SQLite in memory; `DB_DRIVER=mysql` runs the
  identical suite against a real server.
- Removal proof, by stash: config, route and mailable taken back out, the two new test files left in
  place. **9 of 13 new cases turned red** — the two that open a rewritten link, the one that lets a
  rewritten link expire, the three that pin `TrackingParameters`, and the three `mail_headers` cases.
  The remaining four went green in both directions, and that is the point of them: an unnamed
  parameter, an edited payload, an edited signature and a moved `expires` are refused with the change
  and without it. They are not evidence for the fix. They are the fence around it, and a fence that
  only stands after the change would not be one.
- Config: two new keys under `delivery`. A host that publishes the config file gets the defaults on
  the next publish; a host that does not gets them from `mergeConfigFrom`. No migration, no other
  behaviour change.
- The three limits from L15 were re-checked on the hub after this change, with the hidden fields
  stripped from the submission: cell states unchanged, zero notification channels switched on, the
  subscription untouched, the suppression row unreleased. This change touches the signature check of
  one route and the headers of one mail; the limits live in the write path and were not near it.
- **`statamic-marketing` is affected the same way, and was not changed here.** Measured on the hub,
  not inferred: `marketing.confirm` and `marketing.unsubscribe` carry an unguessable token in the
  path with no signature and survive an appended parameter untouched (HTTP 200 with and without).
  `marketing.track.click` is built exactly like this route — `'signed'` middleware plus
  `URL::signedRoute`, with the destination in the query — and behaves exactly like it did: without a
  signature 403, with a valid signature not 403, with a valid signature plus `_se` **403 again**. On
  a Brevo-delivered campaign that is every tracked link, and the click is not recorded either, since
  the middleware refuses before the controller runs. Reported, with the file and line, for a decision
  in that package.
- Verified on the QA hub in the area `preference-center-esp`, the same measurement twice against the
  same seed: the button's URL, rebuilt the way Brevo forwards it, **403 → 200**, while the identical
  URL without the parameter answered 200 in both phases; and the four refusals **403 → 403**.

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
