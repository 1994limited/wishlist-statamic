# Installation

## Requirements

- PHP 8.3 or later.
- Statamic 6.
- Statamic Pro if you want front-end user accounts (lists that follow someone across devices) or the Control Panel permissions. Without Pro, guests can still keep lists.

## Installing

Search for **Wishlist** in **Tools → Addons** in the Control Panel and install it, or run:

```bash
composer require 1994/wishlist-statamic
```

That's all the file driver needs. Lists are kept in `storage/wishlists`, and the **Content → Wishlists** page appears in the Control Panel.

Then add the script to your layout's `<head>`:

```antlers
{{ wishlist:scripts }}
```

and put a save button on the pages that list entries. See [Templates](templates.md).

### Publishing the config

To change any option, publish the config file:

```bash
php please vendor:publish --tag=wishlist-config
```

This writes `config/wishlist.php`. See [Configuration](configuration.md).

### Choosing a driver

The default `file` driver suits most sites. To keep lists in the database, or only in visitors' browsers, see [Storage drivers](drivers.md). In short:

```env
WISHLIST_DRIVER=database
```

```bash
php artisan migrate
```

## Licensing

Wishlist is a commercial addon, sold through the [Statamic Marketplace](https://statamic.com/marketplace). You can install it and try it on a local or development site without a licence. A site on a public domain needs a licence: buy one from the Marketplace and add it to the site on [statamic.com](https://statamic.com). Statamic shows a warning in the Control Panel when a production site uses an unlicensed addon.

One licence covers one site. See [LICENSE.md](../LICENSE.md) for the terms.

## Updating

```bash
composer update 1994/wishlist-statamic
```

After updating:

- **Database driver:** run `php artisan migrate`, in case the update adds a migration.
- **Control Panel assets:** Statamic republishes them on `composer update`. If the Wishlists page looks wrong after an update, run `php artisan vendor:publish --tag=wishlist-statamic --force`.
- **The front-end script** is served with its version in the URL, so browsers pick up the new one straight away.

The [changelog](../CHANGELOG.md) says what changed and anything to do when upgrading.

## Uninstalling

```bash
composer remove 1994/wishlist-statamic
```

Then remove the `{{ wishlist:* }}` tags from your templates. Your lists stay where they were kept:

- **File driver:** the YAML files stay in `storage/wishlists`. Delete the folder if you don't need them.
- **Database driver:** the `wishlists` and `wishlist_items` tables stay. Drop them if you don't need them.
- **Browser driver:** lists stay in visitors' browsers until they clear their site data.
