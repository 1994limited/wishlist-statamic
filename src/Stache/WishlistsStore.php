<?php

namespace NineteenNinetyFour\Wishlist\Stache;

use Illuminate\Support\Facades\Log;
use NineteenNinetyFour\Wishlist\Wishlist;
use Statamic\Facades\YAML;
use Statamic\Stache\Stores\BasicStore;
use Statamic\Yaml\ParseException;

/**
 * The file driver's lists, one YAML file each, named by the list's ID.
 * Indexed by owner and share key, as the users store is by email.
 */
class WishlistsStore extends BasicStore
{
    protected $storeIndexes = ['owner', 'share_key'];

    public function key()
    {
        return 'wishlists';
    }

    public function makeItemFromFile($path, $contents)
    {
        try {
            $data = YAML::file($path)->parse($contents);
        } catch (ParseException) {
            Log::warning('Could not parse wishlist file: '.$path);
            $data = [];
        }

        return Wishlist::fromArray(pathinfo($path, PATHINFO_FILENAME), is_array($data) ? $data : [])->initialPath($path);
    }
}
