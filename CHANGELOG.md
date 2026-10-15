# Changelog

## 1.0.0 (2026-10-15)

The first release.

### Added
- **Wishlists** for Statamic 6: visitors save entries (and, optionally, terms) to named lists, and keep several lists with a default.
- **Three storage drivers**: `file` (YAML files indexed by the Stache), `database` (`wishlists` and `wishlist_items` tables), and `browser` (the visitor's `localStorage`, nothing kept on the server). Set with `WISHLIST_DRIVER`.
- **Share links**: a random key per list with the file and database drivers, which can be reset; the items themselves in the URL with the browser driver. A read-only shared page in your layout, with **Save these to my wishlist**.
- **A share button** (`data-wishlist-native-share`) that opens the device's share sheet with the list's link, and copies it where there's no share sheet. Copying works on http sites too.
- **Tags**: `wishlist`, `lists`, `items`, `has`, `count`, `share_url`, form tags for every change, `toggle_attrs`, `render` (draw a list with your own partial, notes included, with every driver), `scripts`, `success` and `error`.
- **The front-end script** (`{{ wishlist:scripts }}`): `window.Wishlist`, `data-wishlist-*` attributes for buttons, counts, a list picker, share links and copying, and events. The right label shows from the first paint, and it works on statically cached pages.
- **Guests** by cookie, merged into the account on sign-in. `php please wishlist:prune` deletes old guest lists.
- **Item fields**: notes and quantity kept with each item, configurable.
- **Control Panel**: Content → Wishlists, with search, each list's items and share link, the most saved entries, and **View visitors' wishlists** and **Delete wishlists** permissions.
- **`php please wishlist:transfer`** copies lists between the file and database drivers, keeping IDs, share keys and dates.
- **Events**: `ItemAdded`, `ItemRemoved`, `WishlistSaved`, `WishlistDeleted`, `GuestListsMerged`.
- **Documentation** in `docs/`, and a Marketplace brand kit in `docs/marketplace/`.

### Safety
- **Visitors' text is escaped** wherever tags and pages print it: list titles, notes and item fields, and any title carried in a link.
- **Changes are rate limited** per visitor (`throttle`, 60 a minute by default).
- **Only plain text is read from requests.** Anything else counts as missing, and gets a normal answer rather than an error.
- **Stored lists are read leniently.** A malformed or hand-edited list file, or corrupted `localStorage`, is cleaned up rather than breaking the other lists.
