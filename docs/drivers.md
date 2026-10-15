# Storage drivers

A driver decides where lists are kept. Set it with `WISHLIST_DRIVER` in `.env` (or `driver` in [the config](configuration.md)).

| | `file` (default) | `database` | `browser` |
| --- | --- | --- | --- |
| Lists are kept in | YAML files, one per list | Two database tables | The visitor's `localStorage` |
| Lists follow a signed-in user across devices | Yes | Yes | No |
| Guest lists | Yes, by cookie | Yes, by cookie | Yes, in the browser |
| Tags can show lists on the server | Yes | Yes | No: the script draws them |
| Share links | `/wishlist/shared/{key}` | `/wishlist/shared/{key}` | `/wishlist/shared?i=…&t=…` |
| Control Panel and "Most saved" | Yes | Yes | Nothing to show |
| Events (`ItemAdded`, …) | Yes | Yes | No |
| Personal data on your server | Yes | Yes | None |

Templates are written the same way for all three, so you can change driver later without touching them. See [Templates](templates.md).

## File

```env
WISHLIST_DRIVER=file
```

Each list is a YAML file named by its ID, in `storage/wishlists` by default:

```yaml
# storage/wishlists/3f6c…e21.yaml
title: 'My Wishlist'
owner: 'user:jo'
share_key: zZsKAAT75wwQkoesaNFtKMls
default: true
created_at: '2026-10-15T11:07:02+00:00'
updated_at: '2026-10-15T11:07:02+00:00'
items:
  -
    id: 00000005-aaaa-4aaa-8aaa-000000000005
    added_at: '2026-10-15T11:06:23+00:00'
    data:
      notes: 'Size M'
```

The files are indexed and cached by the Stache, as entries are. `php please stache:warm` and `php please stache:clear` include them, and a file you add or change by hand is picked up when the Stache watcher is on.

Lists are visitors' data, not content, so they sit beside form submissions in `storage/` and aren't committed to Git. To keep them somewhere else, set `drivers.file.directory`. If you deploy by replacing the whole site directory, make sure that folder is kept between deploys, as you would for `storage/forms`.

The file driver is a good fit for most sites. With many thousands of lists, the database driver is quicker for the Control Panel's list and its "Most saved" panel.

## Database

```env
WISHLIST_DRIVER=database
```

```bash
php artisan migrate
```

This makes two tables:

- `wishlists`: `id` (UUID), `owner`, `title`, `share_key` (unique), `is_default`, `created_at`, `updated_at`.
- `wishlist_items`: `id`, `wishlist_id`, `reference`, `data` (JSON), `added_at`. One row per item, unique per list, deleted with its list.

The migration only runs while the driver is `database`. To keep it with your own migrations instead, publish it:

```bash
php please vendor:publish --tag=wishlist-migrations
```

To use a connection other than the default, set `WISHLIST_DB_CONNECTION`. Table names are set in `drivers.database`.

## Browser

```env
WISHLIST_DRIVER=browser
```

Lists are kept in the visitor's `localStorage` under `statamic.wishlist`. Nothing is stored on your server: no cookie, no files, no rows.

What that means:

- **Lists stay on one device.** Signing in doesn't bring them along, and clearing site data removes them.
- **The server can't see them.** Tags like `{{ wishlist:has }}` and `{{ wishlist:count }}` read as empty. The script fills in buttons, counts and lists once the page loads, using the `data-wishlist-*` attributes and `{{ wishlist:render }}`. See [JavaScript](javascript.md).
- **Share links carry the items.** A link is a snapshot of the list when it was copied, and doesn't include the notes kept with items. See [Share links](sharing.md).
- **Notes are drawn too.** `{{ wishlist:render }}` gets each item's notes and quantity: the script sends them with the list.
- **No events, no Control Panel data, no "Most saved".**
- **Lists stay in step across open tabs.**

Choose the browser driver when you don't want to hold visitors' data, or the site has no accounts and lists don't need to follow anyone between devices.

## Moving between file and database

Copy every list from one driver to the other:

```bash
php please wishlist:transfer --from=file --to=database
```

Then switch `WISHLIST_DRIVER`. Lists keep their IDs, share keys and dates, so links people already have keep working. A list with the same ID in the target is replaced. Add `--force` to skip the question.

The command copies; it doesn't delete. Remove the old files (or tables) once you're happy.

Lists in visitors' browsers can't be moved: the server never had them.
