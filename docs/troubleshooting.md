# Troubleshooting

## Buttons do nothing

- **Is `{{ wishlist:scripts }}` on the page?** Without it, `data-wishlist-toggle` buttons do nothing, and with the browser driver nothing works. Form tags still work as plain form posts with the file and database drivers.
- **Check the browser's console and network tab** for requests to `/!/wishlist/…`. A `419` means the CSRF token expired: see below. A `422` means the entry can't be saved: see the next question.
- **Browser driver and a plain form?** Without the script, forms post to the server, which keeps nothing with this driver and answers `400`.

## The copy button does nothing

- **Is it disabled?** It is until the visitor has a list: there's no link to copy yet.
- **Is the site on http?** Browsers only allow the modern clipboard on https (and `localhost`). On http, Wishlist falls back to the older way of copying, which some browsers block. If that fails too, the button gets `data-wishlist-copy-failed` and the share link's input is selected, ready to copy by hand.

## "That can't be added to a wishlist."

The entry isn't published, isn't in a collection listed in `collections`, or isn't an entry. Terms need their taxonomy listed in `taxonomies`. See [What can be saved](configuration.md#what-can-be-saved).

## A list I made doesn't show

New lists are added and picked in a `<select data-wishlist-lists>`. Without one on the page, `{{ wishlist:render }}` and the forms keep showing the default list. Add the picker (see [Several lists](templates.md#5-several-lists)), or loop over `{{ wishlist:lists }}`.

## Tags show nothing (browser driver)

That's expected: the server can't see lists kept in a browser. Use the `data-wishlist-*` attributes and `{{ wishlist:render }}`, which the script fills in. See [JavaScript](javascript.md).

## The count or buttons are wrong on cached pages

With static caching, what tags print is cached for every visitor. Use `data-wishlist-count`, `data-wishlist-when` and `{{ wishlist:render }}`, which the script corrects once the page loads, or wrap the tags in `{{ nocache }}`.

## 419 errors

The CSRF token expired, usually on a page left open a long time or cached. The script fetches a fresh token when the page loads. If you call the [HTTP API](http-api.md) yourself, get a token from `/!/wishlist/state` first.

## "Incomplete object" or 500 errors after an update (file driver)

Lists are cached by the Stache. If the cache holds lists from an older version, clear it:

```bash
php please stache:clear
php artisan cache:clear
```

## The shared page looks unstyled or breaks

It renders in the layout set by `share.layout` (`layout` by default). If your site's layout is named differently, set it. To change the page itself, publish its template: see [The shared page](sharing.md#the-shared-page).

## Share links are very long (browser driver)

Browser driver links carry the items, about 23 characters each. To keep them short, use the file or database driver, whose links are a short key. See [Share links](sharing.md).

## Guests' lists disappear

Guest lists belong to a cookie. They're gone for that visitor if they clear cookies, switch browser, or the cookie expires (a year by default). Signing in before saving keeps lists with the account. `wishlist:prune` also deletes guest lists nobody has changed for the number of days you give it.

## The Control Panel page is missing

You need the **View visitors' wishlists** permission (super users have it). If the page looks broken after an update, republish its assets:

```bash
php artisan vendor:publish --tag=wishlist-statamic --force
```
