<?php

namespace NineteenNinetyFour\Wishlist\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\Wishlist\Item;
use NineteenNinetyFour\Wishlist\Wishlist;

class ItemAdded
{
    use Dispatchable;

    public function __construct(public readonly Wishlist $wishlist, public readonly Item $item) {}
}
