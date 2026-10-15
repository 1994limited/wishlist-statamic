<?php

namespace NineteenNinetyFour\Wishlist\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\Wishlist\Wishlist;

class WishlistDeleted
{
    use Dispatchable;

    public function __construct(public readonly Wishlist $wishlist) {}
}
