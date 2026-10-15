# JavaScript

`{{ wishlist:scripts }}`, in your layout's `<head>`, adds a small deferred script (about 6 KB gzipped, no dependencies, no build step), its settings, and the CSS that shows the right `data-wishlist-when` label before the script has run. It works the same with every driver: with the file and database drivers it talks to [the server](http-api.md); with the browser driver it keeps lists in `localStorage`.

Most sites need no JavaScript of their own: the attributes below cover buttons, counts, pickers, share links and lists. For anything else there's `window.Wishlist`.

## Attributes

| Attribute | What the script does |
| --- | --- |
| `form[data-wishlist-form]` | The forms the tags render. Sends them with `fetch()`, sets `aria-busy` while sending, and `data-wishlist-error="…"` if it fails. Make and rename forms are cleared afterwards. |
| `data-wishlist-toggle="id"` | Clicking saves or unsaves the entry. `aria-pressed` and `data-wishlist-active` follow its state. Add `data-wishlist-list="id"` for a list other than the default. |
| `data-wishlist-when="added"` / `"not-added"` | Inside a toggle button or form: shown only in that state. |
| `data-wishlist-count` | Text set to the number of items: on the default list, on `="list id"`, or on all lists with `="all"`. |
| `select[data-wishlist-lists]` | Filled with the visitor's lists. Picks which list is drawn, shared and acted on by list forms that don't name one. A new list is picked as it's made. |
| `data-wishlist-share` | A link's `href`, an input's value, or the element's text, set to the share link of the picked list (or `="list id"`). |
| `data-wishlist-copy-share` | Copies the share link (the picked list, or `="list id"`). Disabled while there's no list to share. Gets `data-wishlist-copied` for two seconds afterwards. If the browser won't copy, it gets `data-wishlist-copy-failed` and the `data-wishlist-share` input is selected, so the visitor can copy it themselves. |
| `data-wishlist-native-share` | Opens the device's share sheet (Messages, WhatsApp, Mail…) with the picked list's title and link (or `="list id"`). `data-wishlist-share-text="…"` adds a message; `:title` in it becomes the list's title. Where there's no share sheet (most desktop browsers, and any http page) it copies the link instead, as `data-wishlist-copy-share` does. Disabled while there's no list to share. |
| `html[data-wishlist-can-share]` | `"true"` or `"false"`: whether this device has a share sheet here. Use it to show a Share button only where it opens one. |
| `data-wishlist-render` | Rendered by `{{ wishlist:render }}`. Drawn again when its list changes. |

Elements added to the page later (by your own scripts, say) work too: clicks and submits are handled at the document. Their state is filled in at the next change, or straight away if you call `Wishlist.pick(Wishlist.picked())`.

## `window.Wishlist`

Wait for it to be ready, then read and change lists. Every change returns a promise, which rejects with an `Error` whose `message` can be shown to the visitor.

```js
await Wishlist.ready;

// Reading (synchronous, from what the script already has)
Wishlist.lists();                 // [{ id, title, default, items: [{ id, added_at, data }], share_url }]
Wishlist.list(listId);            // one list; the default without an ID
Wishlist.has(entryId, listId);    // listId optional; 'any' for any list
Wishlist.count(listId);           // listId optional; 'all' for every list
Wishlist.shareUrl(listId);        // the share link
Wishlist.canShare();              // whether the device has a share sheet here
Wishlist.picked();                // the list picked in a [data-wishlist-lists] select

// Changing
await Wishlist.add(entryId, { list, data: { notes: 'Size M', quantity: 2 } });
await Wishlist.remove(entryId, { list });
await Wishlist.toggle(entryId, { list, data });
await Wishlist.clear(listId);
await Wishlist.createList('Birthday');
await Wishlist.renameList(listId, 'Birthday ideas');
await Wishlist.deleteList(listId);
await Wishlist.makeDefault(listId);
await Wishlist.resetShareLink(listId);              // server drivers
await Wishlist.import(['id1', 'id2'], listId);       // or an encoded string from a share link
Wishlist.pick(listId);                               // pick a list, as the select does

// Asking the server about entries
await Wishlist.resolve(['id1', 'id2']);   // [{ id, title, url }]: the fields in resolve_fields
await Wishlist.reload();                  // fetch the lists again
```

Each change resolves with `{ lists, list, … }`. `toggle`, `add` and `remove` add `added` (whether the entry is on the list now), and `import` adds `imported` (how many were new).

### Drawing lists yourself

To draw a list with your own JavaScript rather than a partial, get the entries' fields from the server:

```js
const list = Wishlist.list();
const entries = await Wishlist.resolve(list.items.map((item) => item.id));

container.innerHTML = entries.map((e) => `<a href="${e.url}">${e.title}</a>`).join('');
```

`resolve()` only returns entries that can be saved and are still published. The fields it returns are set by [`resolve_fields`](configuration.md#the-script).

## Events

On `document`:

| Event | `event.detail` | When |
| --- | --- | --- |
| `wishlist:ready` | `{ lists }` | The script has the visitor's lists. |
| `wishlist:change` | `{ lists, result }` | Anything changed, here or (browser driver) in another tab. |
| `wishlist:error` | `{ message }` | A change failed. |
| `wishlist:copied` | `{ url }` | A share link was copied. |
| `wishlist:shared` | `{ url }` | A share link was sent from the share sheet. |

```js
document.addEventListener('wishlist:change', (event) => {
    console.log(`${Wishlist.count('all')} saved`);
});

document.addEventListener('wishlist:error', (event) => {
    alert(event.detail.message);
});
```

Or with `Wishlist.on(fn)`, which returns a function to stop listening.

## Static caching

The script fetches the visitor's lists and a fresh CSRF token from `/!/wishlist/state` when the page loads, so it works on fully cached pages. Use the attributes above for anything personal on a cached page: what tags print is cached for everyone.

## Updating

The script's URL carries a hash of the file (`/!/wishlist/wishlist.js?v=…`), so it's cached by browsers for a year and a new version is picked up as soon as it's released.
