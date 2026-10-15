# The demo shop

**Dog-ear Books** is a small, made-up bookshop built with Wishlist, at [1994limited/wishlist-statamic-demo](https://github.com/1994limited/wishlist-statamic-demo). Every screenshot in these docs comes from it. Run it to try Wishlist before you build with it, or copy its templates as a starting point.

![Dog-ear Books' shelves, with three books saved by their bookmark ribbons](images/shop.png)

It shows:

- **Saving with a ribbon.** Each cover's bookmark ribbon is a `{{ wishlist:toggle }}` form.
- **Notes.** A book page saves a "who's it for?" note with the item.
- **Several lists**, with a picker, rename, delete and "Start another list".
- **Sharing**, with a link to copy, the phone's share sheet, and a shared page with "Save all to my list".
- **Guests and accounts.** Save as a guest, sign in, and what you saved moves to your account.
- **The Control Panel**, with everyone's lists and the books saved most.

## Running it

You need PHP 8.3+ and Composer.

```bash
git clone https://github.com/1994limited/wishlist-statamic-demo.git
cd wishlist-statamic-demo
composer install
cp .env.example .env
php artisan key:generate
php artisan demo:users
php artisan serve
```

Open http://localhost:8000. `demo:users` makes `jo@example.com`, a shopper, and `admin@example.com`, for the Control Panel at `/cp`. Both use the password `password`, so it only runs with `APP_ENV=local`. Running Wishlist locally needs no licence.

To try the other [drivers](drivers.md), set `WISHLIST_DRIVER` in `.env` to `database` (then `php artisan migrate`) or `browser`. The templates don't change.

## Where to look

| File | Shows |
| --- | --- |
| `resources/views/layout.antlers.html` | `{{ wishlist:scripts }}` in `<head>`, the header count, messages |
| `resources/views/partials/ribbon.antlers.html` | A save button: `{{ wishlist:toggle }}` with `data-wishlist-when` labels |
| `resources/views/products/show.antlers.html` | Saving with a note: `{{ wishlist:add }}` |
| `resources/views/wishlist.antlers.html` | The list picker, share box and list forms |
| `resources/views/partials/saved.antlers.html` | The partial `{{ wishlist:render }}` draws lists with |
| `resources/views/wishlist/shared.antlers.html` | The shared page, with `{{ wishlist:import }}` |
| `public/css/dogear.css` | The styles, including the ribbon |

The demo's own code is MIT licensed, so copy whatever helps. [Examples](examples.md) has the same patterns, trimmed down.
