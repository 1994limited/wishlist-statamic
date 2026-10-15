# Configuration

Wishlist works without any configuration. To change something, publish the config:

```bash
php please vendor:publish --tag=wishlist-config
```

and edit `config/wishlist.php`.

## Storage

| Option | Default | What it does |
| --- | --- | --- |
| `driver` | `env('WISHLIST_DRIVER', 'file')` | `file`, `database` or `browser`. See [Storage drivers](drivers.md). |
| `drivers.file.directory` | `storage_path('wishlists')` | Where the file driver writes one YAML file per list. |
| `drivers.database.connection` | `env('WISHLIST_DB_CONNECTION')` | The database connection. Blank uses the default. |
| `drivers.database.table` | `wishlists` | The lists table. |
| `drivers.database.items_table` | `wishlist_items` | The items table. |
| `drivers.browser.storage_key` | `statamic.wishlist` | The `localStorage` key lists are kept under. |

## Guests

| Option | Default | What it does |
| --- | --- | --- |
| `guests` | `true` | Let visitors who aren't signed in keep lists, tied to a cookie. Off, they're asked to sign in. |
| `guest_cookie` | `wishlist_guest` | The cookie's name. |
| `guest_cookie_minutes` | one year | How long the cookie lasts. |
| `merge_on_login` | `true` | When a guest signs in, move their lists to their account. |

These don't apply to the browser driver, where lists belong to whoever is using the browser. See [Guests and signing in](guests.md).

## What can be saved

| Option | Default | What it does |
| --- | --- | --- |
| `collections` | `null` | Collection handles whose entries can be saved, such as `['products']`. `null` allows any collection. |
| `taxonomies` | `[]` | Taxonomy handles whose terms can be saved. None by default. |
| `item_fields` | `['notes', 'quantity']` | Values a visitor can keep with an item. Anything else sent with an item is dropped. |
| `limits.lists` | `20` | The most lists one visitor can keep. |
| `limits.items` | `500` | The most items one list can hold. |
| `throttle` | `60,1` | How many changes (saves, new lists…) one visitor can make: attempts, then minutes, as for Laravel's `throttle` middleware. Over the limit they get a 429 until it resets. Reading lists isn't limited. `null` turns it off. |
| `default_list_title` | `My Wishlist` | The title of a visitor's first list, made when they first save something. |

Only published entries can be saved. In a dated collection that hides future or past entries, scheduled and expired entries count as unpublished, as they 404 on the site. Entries unpublished, expired or deleted later drop out of lists when they're shown.

Item fields are kept as plain text up to 1,000 characters, with whole HTML tags removed (a lone `<`, as in "I <3 this", stays). They're escaped wherever they're printed. `quantity` is kept as a whole number from 1 to 9,999. Any name you add to `item_fields` is kept as text.

## Share links

| Option | Default | What it does |
| --- | --- | --- |
| `share.enabled` | `true` | Off, there are no share links and the shared page returns 404. |
| `share.route` | `wishlist/shared` | Where shared lists are shown. |
| `share.template` | `wishlist::shared` | The template for the shared page. Publish the addon's with `--tag=wishlist-views` and set this to `wishlist/shared` to edit it. |
| `share.layout` | `layout` | The layout the shared page renders in. |
| `share.max_items` | `200` | The most items a browser driver share link carries. |

See [Share links](sharing.md).

## The script

| Option | Default | What it does |
| --- | --- | --- |
| `resolve_fields` | `['title', 'url']` | The fields `Wishlist.resolve()` and `/!/wishlist/resolve` return for each item. Keep it to what your page shows. |

## Translations

Messages shown to visitors ("Added to My Wishlist.", "A wishlist can hold up to 500 items.") are in the `wishlist::messages` translation file. To change them, or add a language, copy `vendor/1994/wishlist-statamic/lang/en/messages.php` to `lang/vendor/wishlist/{locale}/messages.php` and edit it.
