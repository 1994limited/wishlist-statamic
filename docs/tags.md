# Tags

Every tag is `{{ wishlist:… }}`. Wherever a tag takes `list="…"`, it means a list's ID. Leave it out for the visitor's default list.

**With the browser driver** the server can't see anyone's lists. The tags that read lists (`wishlist`, `list`, `lists`, `items`, `has`, `count`, `share_url`) read as empty, and the script fills in the page instead. See [Templates](templates.md) for the patterns that work with every driver.

**Visitors' text is already escaped.** List titles, notes and other item fields are written by visitors, so the tags give them HTML-escaped, safe to print in text or inside an attribute. Don't add `| entities` or `| sanitize` to them (you'd see `&amp;`), and don't print them with `| raw`-style modifiers that undo it.

## Reading lists

### `{{ wishlist }}` / `{{ wishlist:list }}`

One list, as a pair. The default list, or `list="id"`.

```antlers
{{ wishlist }}
    {{ title }} – {{ count }} items
    {{ items }}{{ title }}{{ /items }}
{{ /wishlist }}
```

| Variable | |
| --- | --- |
| `id` | The list's ID. Empty when the visitor has no lists yet. |
| `title` | The list's title. "My Wishlist" (`default_list_title`) when they have none yet. |
| `is_default` | Whether it's the visitor's default list. |
| `count` / `items_count` | How many items it holds that can still be shown. |
| `no_results` | True when it's empty. |
| `items` | The items: see [Item variables](#item-variables). |
| `share_url` | Its share link. |
| `created_at` / `updated_at` | Dates. |

### `{{ wishlist:lists }}`

Each of the visitor's lists, the default first, with the same variables as above. With no lists, the contents are rendered once with `no_results`.

```antlers
{{ wishlist:lists }}
    {{ if no_results }}No lists yet.{{ else }}<a href="#{{ id }}">{{ title }} ({{ count }})</a>{{ /if }}
{{ /wishlist:lists }}
```

Inside the loop, the list forms (`rename_list`, `delete_list` and the rest) act on that loop's list.

### `{{ wishlist:items }}`

The items on a list, as a pair.

| Parameter | |
| --- | --- |
| `list` | A list's ID. Default list if left out. |
| `limit` | Show at most this many. |

With no items, the contents are rendered once with `no_results`.

#### Item variables

Each item has every field of its entry (`title`, `url`, `price`, …), plus:

| Variable | |
| --- | --- |
| `wishlist_item:id` | The entry's ID (or `taxonomy::slug` for a term). |
| `wishlist_item:added_at` | When it was saved. Use with `format`: `{{ wishlist_item:added_at format="j M Y" }}`. |
| `wishlist_item:notes` | What the visitor noted, if anything. |
| `wishlist_item:quantity` | The quantity they set, if any. |
| `wishlist_item:{field}` | Any other [item field](configuration.md#what-can-be-saved). |

Entries that have since been unpublished or deleted are left out.

### `{{ wishlist:has }}`

Whether an entry is on a list. Alias: `{{ wishlist:in_list }}`.

| Parameter | |
| --- | --- |
| `id` (or `entry`) | The entry's ID. |
| `list` | A list's ID, `any` for any of the visitor's lists, or leave it out for the default list. |

```antlers
{{ if {wishlist:has id="{{ id }}"} }}♥{{ /if }}
```

### `{{ wishlist:count }}`

How many items are on a list: the default, `list="id"`, or `list="all"` for all the visitor's lists together.

### `{{ wishlist:share_url }}`

A list's share link: the default list, or `list="id"`. With the browser driver, use `data-wishlist-share` instead (see [JavaScript](javascript.md)).

### `{{ wishlist:encoded }}`

A server driver list's items packed as a browser-style share parameter, for building links by hand: `/wishlist/shared?i={{ wishlist:encoded }}`.

## Changing lists

Each of these renders a `<form>` that posts to the matching [action](http-api.md). With the script on the page it's sent with `fetch()` and the page updates in place; without it, the page reloads. Put your inputs and button between the tags.

They all take:

| Parameter | |
| --- | --- |
| `redirect` | Where a plain form post goes afterwards. Default: back to the page. Only addresses on your own site are followed. |
| `error_redirect` | Where it goes when something is wrong. Default: back to the page. |
| `csrf="false"` | Leave out the CSRF field (when the page adds its own). |
| anything else | Becomes an attribute of the `<form>`: `class="…"`, `id="…"`, `attr:aria-label="…"`. |

### `{{ wishlist:toggle }}`, `{{ wishlist:add }}`, `{{ wishlist:remove }}`

Save or unsave an entry.

| Parameter | |
| --- | --- |
| `id` (or `entry`) | The entry's ID. |
| `list` | A list's ID. Default list if left out. |

Inside the tag, `{{ added }}` (or `{{ in_list }}`) says whether the entry is on the list when the page is rendered. Inputs named after [item fields](configuration.md#what-can-be-saved) are saved with the item:

```antlers
{{ wishlist:add id="{{ id }}" }}
    <input name="notes" placeholder="Size, colour…">
    <input name="quantity" type="number" value="1" min="1">
    <button type="submit">Add to wishlist</button>
{{ /wishlist:add }}
```

### `{{ wishlist:toggle_attrs }}`

Attributes for a button of your own, which the script makes save and unsave the entry: `data-wishlist-toggle`, `data-wishlist-list` and `aria-pressed`.

```antlers
<button class="heart" {{ wishlist:toggle_attrs id="{{ id }}" }}>♥</button>
```

Takes `id` (or `entry`) and `list`. Needs the script.

### `{{ wishlist:create_list }}`

Makes a new list. Include an input named `title`. The first list a visitor makes becomes their default.

### `{{ wishlist:rename_list }}`, `{{ wishlist:delete_list }}`, `{{ wishlist:clear_list }}`, `{{ wishlist:make_default }}`

Act on one list:

- inside `{{ wishlist:lists }}`, that loop's list;
- otherwise `list="id"`;
- otherwise, with the script, the list picked in a `<select data-wishlist-lists>`;
- otherwise the default list.

`rename_list` needs an input named `title`. Deleting the default list makes the next oldest the default.

### `{{ wishlist:reset_share_link }}`

Gives a list a new share link. Links already sent stop working. File and database drivers only: a browser driver link carries the items themselves, so there's nothing to reset.

### `{{ wishlist:import }}`

For the [shared page](sharing.md#the-shared-page): copies the shared list's items onto the visitor's own default list (or `list="id"`). It reads what it needs from the page, so it needs no parameters there.

```antlers
{{ unless is_owner }}
    {{ wishlist:import }}<button type="submit">Save these to my wishlist</button>{{ /wishlist:import }}
{{ /unless }}
```

## Rendering and the script

### `{{ wishlist:render }}`

Draws a list with a partial of your own, and draws it again whenever the list changes. This is the way to show lists with every driver.

| Parameter | |
| --- | --- |
| `partial` | The partial: `wishlist` means `resources/views/partials/wishlist.antlers.html`. |
| `list` | A list's ID. Without it, the default list, or the list picked in a `<select data-wishlist-lists>`. |
| `class` | A class for the wrapping `<div>`. |

The partial gets `title`, `items` (with the [item variables](#item-variables)), `count` and `no_results`. With the browser driver, anything between the tags shows until the script has drawn the list. The partial's name is signed, so visitors can't ask your server to render any other view.

### `{{ wishlist:scripts }}`

The script (deferred), its settings, and a few lines of CSS that show the right `data-wishlist-when` label from the first paint. Put it once in your layout's `<head>`. See [JavaScript](javascript.md).

### `{{ wishlist:success }}` / `{{ wishlist:error }}`

The message after a plain (non-script) form post: "Added to My Wishlist." or what went wrong.

### `{{ wishlist:driver }}` / `{{ wishlist:stores_on_server }}`

The driver's name, and whether it keeps lists on the server (false for `browser`), for templates shared between sites.
