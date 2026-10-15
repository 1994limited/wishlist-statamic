<?php

namespace NineteenNinetyFour\Wishlist\Listeners;

use Illuminate\Auth\Events\Login;
use NineteenNinetyFour\Wishlist\CurrentOwner;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Wishlists;

/**
 * When a guest signs in, the lists they made as a guest become theirs.
 */
class MergeGuestLists
{
    public function __construct(protected Wishlists $wishlists, protected CurrentOwner $owner) {}

    public function handle(Login $event): void
    {
        if (! config('wishlist.merge_on_login', true) || ! $this->wishlists->storesOnServer()) {
            return;
        }

        if (! $token = $this->owner->guestToken()) {
            return;
        }

        $this->wishlists->merge(Owner::guest($token), Owner::user((string) $event->user->getAuthIdentifier()));
        $this->owner->forgetGuest();
    }
}
