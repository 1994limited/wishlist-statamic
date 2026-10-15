# Changelog

## Unreleased

### Added
- **Wishlists** for Statamic 6: visitors save entries (and, optionally, terms) to named lists, and keep several lists with a default.
- **Three storage drivers**: `file` (YAML files indexed by the Stache), `database` (`wishlists` and `wishlist_items` tables), and `browser` (the visitor's `localStorage`, nothing kept on the server). Set with `WISHLIST_DRIVER`.
- **Share links**: a random key per list with the file and database drivers, which can be reset; the items themselves in the URL with the browser driver. A read-only shared page in your layout, with **Save these to my wishlist**.
- **Tags**: `wishlist`, `lists`, `items`, `has`, `count`, `share_url`, form tags for every change, `toggle_attrs`, `render` (draw a list with your own partial, with every driver), `scripts`, `success` and `error`.
- **The front-end script** (`{{ wishlist:scripts }}`): `window.Wishlist`, `data-wishlist-*` attributes for buttons, counts, a list picker, share links and copying, and events. Works on statically cached pages.
- **Guests** by cookie, merged into the account on sign-in. `php please wishlist:prune` deletes old guest lists.
- **Item fields**: notes and quantity kept with each item, configurable.
- **Control Panel**: Content → Wishlists, with search, each list's items and share link, the most saved entries, and **View visitors' wishlists** and **Delete wishlists** permissions.
- **`php please wishlist:transfer`** copies lists between the file and database drivers, keeping IDs, share keys and dates.
- **Events**: `ItemAdded`, `ItemRemoved`, `WishlistSaved`, `WishlistDeleted`, `GuestListsMerged`.
- **A share button** (`data-wishlist-native-share`) that opens the device's share sheet with the list's link, and copies it where there's no share sheet.
- **Documentation** in `docs/`.

### Fixed
- **The copy link button works on http sites.** The Clipboard API only exists on https, so it now falls back to the older way of copying, and selects the link if the browser won't copy at all. It's disabled while there's no list to share.
