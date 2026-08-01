# Upgrading

## Installing next to an existing `goldnead/statamic-marketing`

> **Affects every host that had marketing before it had this package.** If this is a fresh install
> with no marketing history, skip to [After the cutover](#after-the-cutover).

For a while there were two preference pages. `goldnead/statamic-marketing` served its own at
`/!/marketing/preferences/{token}`, this package serves the combined one at
`/!/preference-center/t/{pcToken}`, and the two were near-identical by construction — same
`data-list`/`data-state` contract, same error split, same "unsubscribe from everything" second form.
A person clicking a marketing footer link landed on the single-list page and never learned that the
same address was on four other lists of the same brand.

That is now decided: **this package owns the preference page. Marketing keeps only unsubscribe.**

### What changes on your site

| Before | After |
|---|---|
| `/!/marketing/preferences/{token}` renders marketing's own page | The route is gone from marketing |
| Marketing's footer links point at that page | Marketing asks this package for the URL and falls back to its own unsubscribe path when this package is absent |
| Lists only | Lists, notification matrix, cadence and block state, from whichever packages are installed |
| Unsubscribing was a preference page | Unsubscribing is its own one-click path in marketing, and it works with this package uninstalled |

The one-click path staying in marketing is deliberate. Unsubscribing is a legal obligation and an
RFC 8058 endpoint that mail providers POST to unattended; it may not depend on an optional package
being installed. Only the *richer* preference page moved here.

### The steps

**1. Upgrade both packages together.**

```bash
composer update goldnead/statamic-preference-center goldnead/statamic-marketing --with-dependencies
```

Do not upgrade one without the other. A marketing that still serves its own page next to this one puts
two live preference pages on the same site again; a marketing that has already dropped its page next
to a preference-center that predates the discovery interface leaves its footer links pointing nowhere.

**2. Clear the caches that hold a route table.** This is not optional here. The token door in this
package is registered only when marketing is installed, so a cached route table built before the
upgrade disagrees with reality afterwards — and disagrees silently.

```bash
php artisan route:clear
php artisan view:clear
php artisan config:clear
```

If you cache routes in production (`php artisan route:cache`), rebuild the cache after deploying, and
rebuild it again any time you add or remove `goldnead/statamic-marketing`.

**3. Point the notifications footer at the combined page** if you had it pointing anywhere else:

```dotenv
NOTIFICATIONS_PREFERENCES_URL="https://example.com/!/preference-center"
```

**4. Republish the views only if you had forked marketing's partial.** If you published
`resources/views/vendor/marketing/partials/preferences.blade.php` and edited it, those edits are now
dead — the partial no longer has a route rendering it. Move them to this package's views:

```bash
php artisan vendor:publish --tag=preference-center-views
```

The two templates use the same class names and the same `data-*` contract, so most customisations
transplant directly. What is new here and has no counterpart in marketing's partial: the notification
matrix (`data-cell`), the cadence radios (`data-frequency`), the page-level suppression banner
(`data-suppression`) and the empty state for a host with nothing installed (`data-block="none"`).

**5. Check your own code for references to the removed route.**

```bash
grep -rn "marketing.preferences" app resources config
```

Anything that built a preference URL by hand should go through the discovery interface instead:

```php
use Goldnead\PreferenceCenter\PreferenceCenter;

$url = class_exists(PreferenceCenter::class)
    ? app(PreferenceCenter::class)->urlForToken($subscription->token)
    : null;

$url ??= route('marketing.unsubscribe', $subscription->token);
```

Probe `class_exists()` on that class. Not `method_exists()` on the facade — a facade answers through
`__callStatic`, so the check is `false` while the method exists, which is how every LeadHub action
node broke in `goldnead/statamic-automations` v1.0.3.

### What happens to links already in people's inboxes

Nothing you can recall. Mail that is already delivered still carries
`/!/marketing/preferences/{token}` URLs, and once marketing removes that route those links 404.

Pick one before you deploy:

- **Redirect them.** One line in your application's routes, kept for as long as your oldest live
  campaign is worth honouring — a year is a reasonable default for a newsletter footer:

  ```php
  Route::get('/!/marketing/preferences/{token}', function (string $token) {
      $url = class_exists(\Goldnead\PreferenceCenter\PreferenceCenter::class)
          ? app(\Goldnead\PreferenceCenter\PreferenceCenter::class)->urlForToken($token)
          : null;

      return redirect($url ?? route('marketing.unsubscribe', $token));
  })->name('marketing.preferences.legacy');
  ```

  The token is the same value in both packages, which is what makes this a redirect rather than a
  migration.

- **Or accept the 404** if you have never sent a mail carrying that URL. Check before you assume:
  the link lived in marketing's own campaign footers.

The unsubscribe links in old mail are unaffected — that path did not move.

### After the cutover

Verify, in this order:

1. `php artisan route:list --name=preference-center` lists seven routes, including
   `preference-center.token`. If the token routes are missing, marketing is not installed or
   `preference-center.sources.marketing` is `false`.
2. Open a real token URL from a real subscription. The page shows the lists block *and* whichever of
   the notification and cadence blocks your installation supports.
3. Send yourself a campaign and click the preference link in the footer. It should land on
   `/!/preference-center/t/…`, not on anything under `/!/marketing/`.
4. Click the unsubscribe link in the same mail. It should still work, and it should still work with
   this package temporarily uninstalled.

## Version history of this document

Nothing before the cutover above needed an upgrade guide: 1.0.0 through 1.2.0 were additive, and no
published route, config key, view path or facade method changed in them.
