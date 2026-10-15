# Changelog

## 1.2.2 (2026-10-15)

### Safety
- **A form's `_redirect` and `_error_redirect` can't send visitors to another site.** Addresses such as `/\evil.com`, or with a tab or newline after the first slash, passed as paths on this site, but browsers read them as `//evil.com`. Anything with a backslash or control character is now ignored, a path must be `/` or a slash followed by something other than a slash, and a full address must be `http` or `https` on this site's host.

## 1.2.1 (2026-10-15)

### Fixed
- **Remove buttons remove from the list they're on.** Item buttons and forms inside a drawn list (a `{{ wishlist:render }}` partial, `{{ wishlist:lists }}`) now act on that list when given no `list`. Before, with another list picked, Remove acted on the default list instead. Blade partials pass `->param('list', $wishlist_list_id ?? null)`, as `Statamic::tag()` doesn't see the partial's variables.
- **Only supported Cargo versions are used.** Composer won't install Wishlist alongside Cargo older than 1.15, and a Cargo outside 1.15–1.x is left alone rather than failing.
- **Adding a list to the basket skips what can't be saved any more** (unpublished, protected, or a variant the product no longer has), and expected refusals (out of stock) are no longer logged as errors.
- **The browser driver sends quantities** with "add this list to my basket", so they're kept as with the other drivers.
- **The basket form follows the picker** when it changes on the page, not only the list picked when the page loaded.
- **Variants a product no longer has drop off lists** and counts.
- **Saved prices are kept with their currency**, so a multisite shop doesn't compare prices in different currencies. `saved_price` and `saved_currency` can't be set by visitors, even if listed in `item_fields`.
- **A malformed `wishlist_picked` cookie is ignored** instead of stopping the script. Its name can be changed with `picked_cookie`.

### Docs
- Corrected and filled in the Cargo, tags, JavaScript, HTTP API, PHP API, configuration, cookie and troubleshooting docs, and fixed the list partial examples to remove by `wishlist_item:id`.

## 1.2.0 (2026-10-15)

### Added
- **Choose the list when saving.** A `<select name="list" data-wishlist-lists>` inside a save form picks which list it saves to, with every driver, and the button's added state follows it. The list picked in a picker is remembered for the visit (in a `wishlist_picked` session cookie), so it's still picked on the next page, and the server draws that list first time instead of the default. See [Several lists](docs/templates.md#5-several-lists).
- **Cargo.** With [Cargo](https://builtwithcargo.dev) installed, lists show each product's price and stock (`{{ cargo:* }}`), keep the price it was saved at and say when it has dropped, and save variants as separate items (`variant` on the item tags, or a `variant` field in the form). `{{ wishlist:add_to_cart }}` puts a whole list in the basket, leaving out what's sold out or still needs a variant chosen. Gift Vouchers of any amount are treated as needing their amount chosen. Nothing changes without Cargo. See [Cargo](docs/cargo.md), and the new [Dog-ear Books with Cargo](https://github.com/1994limited/wishlist-cargo-demo) demo.

## 1.1.0 (2026-10-15)

### Added
- **Blade.** The tags work from Blade templates, with Statamic's `<s:wishlist:…>` tags and `Statamic::tag()`; `wishlist:render` partials and the shared page can be Blade views. Every example in the README and docs now has a Blade version, and tests cover the main patterns, including escaping.

## 1.0.3 (2026-10-15)

### Fixed
- **A lighter Control Panel icon.** The Wishlists nav icon carried an 8 KB embedded metadata block, inlined into every Control Panel page and the Wishlists page's script. The drawing is unchanged.

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
