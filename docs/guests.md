# Guests and signing in

This page is about the file and database drivers. With the [browser driver](drivers.md#browser), lists belong to whoever is using the browser: there are no accounts or merging, and no cookie but `wishlist_picked` (below).

## Who owns a list

- **Signed-in users** own lists by their user ID. Their lists follow them to any device they sign in on. Front-end accounts need Statamic Pro.
- **Guests** (visitors who aren't signed in) own lists by a cookie, `wishlist_guest`, holding a random ID. It's set the first time they save something, not when they browse, and lasts a year (`guest_cookie_minutes`).

A list is only ever shown or changed by its owner. Anyone else can only see it through its [share link](sharing.md).

To keep wishlists for signed-in users only, set `guests` to `false`. A guest who tries to save is told "Sign in to keep a wishlist." (a 401 to the script), so show them a sign-in link instead of the button. See [Examples](examples.md#signed-in-only).

## Signing in

When a guest signs in, their lists become their account's (`merge_on_login`, on by default):

- The guest's **default list** joins the user's default list. Items already there keep their place, and the list's item limit still applies.
- The guest's **other lists** move to the account as they are.
- The guest's cookie is removed.

This works with Statamic's `{{ user:login_form }}`, the Control Panel's sign-in, and anything else that fires Laravel's `Login` event. Listen for `GuestListsMerged` to do something afterwards (see [PHP API](php-api.md#events)).

Turn merging off with `merge_on_login` set to `false`. The guest's lists then stay with the cookie, and come back if they sign out.

## Cookies and privacy

- `wishlist_guest` is set only once a visitor saves something, so it's a cookie they asked for. Whether that needs consent depends on where you are: check with whoever looks after your cookie policy.
- The cookie holds a random ID, encrypted by Laravel, and nothing else.
- `wishlist_picked` (with every driver) is set when a visitor picks a list in a list picker or makes a new one, and ends with the browser session. It holds that list's ID, so pages arrive showing that list, and the server ignores it unless the list is theirs. Rename it with `picked_cookie`.
- Lists hold entry IDs, the item fields you allow (notes and quantity by default), dates and an owner. No names, emails or IP addresses.
- To keep nothing about visitors on your server, use the [browser driver](drivers.md#browser).

## Pruning old guest lists

Guests clear their cookies and never come back, leaving lists nobody can reach except by a share link. Delete guest lists nobody has changed in 90 days:

```bash
php please wishlist:prune --days=90
```

To run it every week, add it to your schedule in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('wishlist:prune --days=90')->weekly();
```

Signed-in users' lists are never pruned.
