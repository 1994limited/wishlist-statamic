# Wishlist documentation

Wishlist lets visitors save entries to wishlists, keep several named lists, and share any of them with a link. Lists are kept in flat files, in the database, or only in the visitor's own browser. Templates, tags and the script work the same whichever you choose, in Antlers or Blade: every example has both.

## Setting up

1. [Installation](installation.md): requirements, installing, licensing, updating.
2. [Storage drivers](drivers.md): files, the database or the browser, and moving between them.
3. [Configuration](configuration.md): every option in `config/wishlist.php`.

## Building the front end

4. [Templates](templates.md): a save button, a wishlist page, several lists, messages, and [using Blade](templates.md#antlers-or-blade). Start here.
5. [Tags](tags.md): every `{{ wishlist:* }}` tag, its parameters and variables.
6. [JavaScript](javascript.md): `window.Wishlist`, the `data-wishlist-*` attributes, events, static caching.
7. [Share links](sharing.md): how links work with each driver, and the shared page.
8. [Examples](examples.md): complete templates to copy.
9. [The demo shop](demo.md): Dog-ear Books, a whole site built with Wishlist, to run or copy from.
10. [Cargo](cargo.md): prices, stock, variants, price drops and adding a list to the basket, for shops built with Cargo.

## Running a site

11. [Guests and signing in](guests.md): guest lists, merging on sign-in, cookies, pruning.
12. [The Control Panel](control-panel.md): Content → Wishlists, and permissions.

## Reference

13. [HTTP API](http-api.md): the `/!/wishlist/…` endpoints, for headless and custom front ends.
14. [PHP API](php-api.md): the `Wishlists` service, repositories, events, and writing your own driver.
15. [Troubleshooting](troubleshooting.md): common problems and fixes.

## Requirements

- PHP 8.3 or later.
- Statamic 6. The Control Panel's permissions, and more than one user, need Statamic Pro.
- For the database driver: any database Laravel supports.
