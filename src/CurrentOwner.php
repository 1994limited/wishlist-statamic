<?php

namespace NineteenNinetyFour\Wishlist;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Statamic\Facades\User;

/**
 * Who the lists in this request belong to: the signed-in user, or else a
 * guest known by a cookie. The cookie is only set once a guest saves
 * something, so browsing never leaves one behind.
 */
class CurrentOwner
{
    public function __construct(protected Request $request) {}

    public function get(bool $create = false): ?Owner
    {
        if ($user = User::current()) {
            return Owner::user($user);
        }

        if (! config('wishlist.guests', true)) {
            return null;
        }

        if ($token = $this->guestToken()) {
            return Owner::guest($token);
        }

        if (! $create) {
            return null;
        }

        $token = (string) Str::uuid();
        $this->request->attributes->set('wishlist_guest', $token);

        Cookie::queue(config('wishlist.guest_cookie', 'wishlist_guest'), $token, (int) config('wishlist.guest_cookie_minutes', 60 * 24 * 365));

        return Owner::guest($token);
    }

    /**
     * The guest's token, from this request's cookie or one set earlier in
     * the same request.
     */
    public function guestToken(): ?string
    {
        if ($this->request->attributes->get('wishlist_guest_forgotten')) {
            return null;
        }

        $token = $this->request->attributes->get('wishlist_guest')
            ?? $this->request->cookie(config('wishlist.guest_cookie', 'wishlist_guest'));

        return is_string($token) && Str::isUuid($token) ? $token : null;
    }

    public function forgetGuest(): void
    {
        // The cookie in this request is still the guest's; stop reading it.
        $this->request->attributes->remove('wishlist_guest');
        $this->request->attributes->set('wishlist_guest_forgotten', true);

        Cookie::queue(Cookie::forget(config('wishlist.guest_cookie', 'wishlist_guest')));
    }
}
