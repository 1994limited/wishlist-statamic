# HTTP API

The tags and the script use these endpoints. Use them directly for a headless front end, or a script of your own. They're all under Statamic's action prefix, `/!/wishlist/`.

## Requests

- **Same origin, with cookies.** The visitor is the signed-in user, or the guest in the `wishlist_guest` cookie. A guest's first change sets the cookie.
- **POSTs need a CSRF token**, in an `X-CSRF-TOKEN` header or a `_token` field. Get one from `GET /!/wishlist/state`.
- **Send `Accept: application/json`** for JSON answers. Without it, a POST is treated as a plain form: it redirects back (or to `_redirect`), with a message flashed for `{{ wishlist:success }}` or an error for `{{ wishlist:error }}`.
- Bodies can be JSON or form fields.
- **With the browser driver**, every POST except `cart` answers `400` "Wishlists are kept in your browser on this site.", and `state` returns no lists.

## Reading

### `GET /!/wishlist/state`

The visitor's lists and a CSRF token. Never cached.

```json
{
    "driver": "file",
    "csrf": "zEvZwAVLtrUyHHkuTYSMBqUAMfDeeBMuiRhsVxOl",
    "signed_in": true,
    "lists": [
        {
            "id": "afb44e09-724c-42fa-9896-ca4aa87e6da5",
            "title": "My Wishlist",
            "default": true,
            "share_url": "https://example.com/wishlist/shared/zZsKAAT75wwQkoesaNFtKMls",
            "items": [
                { "id": "00000005-aaaa-4aaa-8aaa-000000000005", "added_at": "2026-10-15T11:06:23+00:00", "data": { "notes": "Size M" } }
            ]
        }
    ]
}
```

### `GET /!/wishlist/resolve`

The fields in [`resolve_fields`](configuration.md#the-script) (title and url by default) for entries, given as `ids[]=…&ids[]=…`, or packed as `i=…` (as in browser driver share links). Entries that can't be saved, or aren't published, are left out. Up to `share.max_items`.

```json
{ "items": [ { "id": "00000005-…", "title": "Silk Scarf", "url": "/products/silk-scarf" } ] }
```

### `GET /!/wishlist/render`

HTML: a partial rendered with a list. With the file and database drivers, pass the visitor's list as `list=…` to draw it as stored, notes and all (someone else's list isn't drawn). Otherwise it's drawn from the given entries (`i=…`, and a title as `t=…`, escaped).

### `POST /!/wishlist/render`

The same, for the browser driver, which posts its list with what's kept with each item (too long for a URL). A JSON body:

```json
{ "view": "…signed…", "t": "Birthday", "items": [{ "id": "…", "data": { "notes": "Size M", "quantity": 2 } }] }
```

Item fields are held to the same rules as when saving: only [`item_fields`](configuration.md#what-can-be-saved), plain text, quantity a whole number. It only draws HTML and changes nothing, so it needs no CSRF token. `view` must be the signed value from a `{{ wishlist:render }}` container's `data-wishlist-render`. Anything else answers `400`.

## Changing

Each answers, on success:

```json
{
    "success": true,
    "message": "Added to My Wishlist.",
    "list": { "id": "…", "title": "…", "default": true, "share_url": "…", "items": [ … ] },
    "lists": [ … every list the visitor has, as in state … ]
}
```

and on failure a `4xx` with `{ "success": false, "message": "…" }`. The message is safe to show the visitor.

| Endpoint | Fields | Adds to the answer |
| --- | --- | --- |
| `POST items/add` | `id`, `variant`? ([Cargo](cargo.md#variants)), `list`?, item fields (`notes`, `quantity`, or `data[…]`) | `added: true` |
| `POST items/remove` | `id`, `list`? | `added: false` |
| `POST items/toggle` | `id`, `list`?, item fields | `added`: whether it's on the list now |
| `POST lists/create` | `title` | |
| `POST lists/rename` | `list`?, `title` | |
| `POST lists/delete` | `list`? | `list` is `null` |
| `POST lists/clear` | `list`? | |
| `POST lists/default` | `list` | |
| `POST lists/share` | `list`? | a new `share_url` |
| `POST lists/import` | `key` (a share key), or `ids[]`, or `i`; `list`? | `imported`: how many were new |
| `POST cart` | `list`?, or `i` with the browser driver ([Cargo](cargo.md#adding-a-whole-list-to-the-basket) only) | `cart`: `added` (how many) and `skipped` (the item IDs left out) |

`list` is a list's ID. Left out, it means the visitor's default list, which `add`, `toggle` and `import` make if the visitor has none yet.

### Errors

| Status | Message | When |
| --- | --- | --- |
| `400` | Wishlists are kept in your browser on this site. | Browser driver. |
| `401` | Sign in to keep a wishlist. | `guests` is off and nobody is signed in. |
| `404` | That wishlist could not be found. | The list isn't the visitor's, or doesn't exist. |
| `419` | | The CSRF token is missing or expired. Fetch `state` again. |
| `422` | That can't be added to a wishlist. | The entry isn't published, isn't in an allowed collection, or doesn't exist. |
| `422` | A wishlist can hold up to 500 items. / You can keep up to 20 wishlists. | A [limit](configuration.md#what-can-be-saved). |
| `429` | Too Many Attempts. | Over the [rate limit](configuration.md#what-can-be-saved) for changes. |

Fields are read as plain text. A field sent as anything else (an array, say) counts as missing.

## Example

```js
const state = await fetch('/!/wishlist/state', { headers: { Accept: 'application/json' } }).then((r) => r.json());

const result = await fetch('/!/wishlist/items/toggle', {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': state.csrf },
    body: JSON.stringify({ id: entryId, data: { notes: 'Size M' } }),
}).then((r) => r.json());

result.added; // true
```

## The shared page

`GET /wishlist/shared/{key}` (file and database) and `GET /wishlist/shared?i=…&t=…` (browser) render the [shared page](sharing.md#the-shared-page) as HTML. The path is set by `share.route`.
