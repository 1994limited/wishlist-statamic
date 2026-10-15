<?php

namespace NineteenNinetyFour\Wishlist\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\Wishlist\Owner;

/**
 * A guest signed in and their lists became the user's.
 */
class GuestListsMerged
{
    use Dispatchable;

    public function __construct(public readonly Owner $guest, public readonly Owner $user) {}
}
