<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage driver
    |--------------------------------------------------------------------------
    |
    | Where wishlists are kept.
    |
    | "file"     YAML files, one per list, indexed by the Stache like entries.
    | "database" Two tables (wishlists, wishlist_items), like the Eloquent driver.
    | "browser"  Nowhere on the server: lists live in the visitor's
    |            localStorage, and share links carry the items in the URL.
    |
    */

    'driver' => env('WISHLIST_DRIVER', 'file'),

    'drivers' => [

        'file' => [
            // Visitors' lists are user data, not content, so by default they
            // sit beside form submissions rather than in content/.
            'directory' => storage_path('wishlists'),
        ],

        'database' => [
            'connection' => env('WISHLIST_DB_CONNECTION'),
            'table' => 'wishlists',
            'items_table' => 'wishlist_items',
        ],

        'browser' => [
            // The localStorage key the lists are kept under.
            'storage_key' => 'statamic.wishlist',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Who can keep a wishlist
    |--------------------------------------------------------------------------
    |
    | With guests on, a visitor who isn't signed in gets lists tied to a
    | cookie. When they sign in, their guest lists are merged into their
    | account's lists (merge_on_login). The browser driver ignores these:
    | lists belong to whoever is using the browser.
    |
    */

    'guests' => true,

    'guest_cookie' => 'wishlist_guest',

    'guest_cookie_minutes' => 60 * 24 * 365,

    // The session cookie the script keeps the visitor's picked list in, so
    // pages arrive showing it. Give each site its own name if several share
    // a domain.
    'picked_cookie' => 'wishlist_picked',

    'merge_on_login' => true,

    /*
    |--------------------------------------------------------------------------
    | What can go on a list
    |--------------------------------------------------------------------------
    |
    | Items are references to Statamic data: entry IDs, or "taxonomy::slug"
    | for terms. Limit them to some collections with "collections" (handles),
    | or leave it null to allow entries in any collection with a route.
    | Terms are only allowed when "taxonomies" names their taxonomy. Drafts,
    | scheduled, expired and protected entries are never allowed.
    |
    */

    'collections' => null,

    'taxonomies' => [],

    // Extra values a visitor may store against an item (a size, a note,
    // a quantity). Anything else sent with an item is dropped.
    'item_fields' => ['notes', 'quantity'],

    'limits' => [
        'lists' => 20,
        'items' => 500,
    ],

    'default_list_title' => 'My Wishlist',

    // How many changes (saves, new lists…) one visitor can make, as
    // "attempts,minutes" for Laravel's throttle middleware. Reading lists
    // isn't limited. Set to null to turn it off.
    'throttle' => '60,1',

    /*
    |--------------------------------------------------------------------------
    | Shared lists
    |--------------------------------------------------------------------------
    |
    | Every list has a link that shows it read-only to anyone who has it.
    | With the file and database drivers the link holds a random key
    | (/wishlist/shared/k3Jd9...). With the browser driver it holds the
    | items themselves (/wishlist/shared?i=...&t=...).
    |
    | The page is rendered with your template and layout. The addon's own
    | template is a plain starting point: copy it to
    | resources/views/wishlist/shared.antlers.html and set it here.
    |
    */

    'share' => [
        'enabled' => true,
        'route' => 'wishlist/shared',
        'template' => 'wishlist::shared',
        'layout' => 'layout',
        // The most items a browser share link carries.
        'max_items' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resolving items for JavaScript
    |--------------------------------------------------------------------------
    |
    | Wishlist.resolve() (and the browser driver's rendering) asks the server
    | for these fields of each item. Keep it to what the page shows.
    |
    */

    'resolve_fields' => ['title', 'url'],

];
