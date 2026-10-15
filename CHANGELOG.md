# Changelog

## 1.0.2 (2026-10-15)

### Fixed
- **Only what has a page can be saved.** With `collections` left as `null`, entries in collections without a route can no longer be saved, so `/!/wishlist/resolve` and share links can't give out their titles. Name a collection in `collections` to allow its entries anyway.
- **Protected entries and terms are left out.** Anything with Statamic's `protect` set can't be saved and drops out of lists.
- **Lists show only what could still be added**, so terms drop out once their taxonomy is removed from `taxonomies`, and entries once their collection is removed from `collections`.
- **A shared page carries only the IDs it shows.** The import form no longer includes items that are no longer available.

## 1.0.1 (2026-10-15)

### Fixed
- **Scheduled and expired entries are treated like drafts.** In a dated collection that hides future or past entries, they can't be added, are left out of lists, counts and shared pages, and `/!/wishlist/resolve` no longer gives their title and URL.
- **`wishlist:transfer` checks the database tables exist** before copying, and says what to run if they don't. [Storage drivers](docs/drivers.md#moving-between-file-and-database) now gives the steps in order: publish the migration, migrate, transfer, then switch `WISHLIST_DRIVER`.

### Changed
- The package now includes `docs/images`, which the docs link to, and leaves out the marketplace listing images except the README's cover.

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
