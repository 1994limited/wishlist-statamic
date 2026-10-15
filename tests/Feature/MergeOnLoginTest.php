<?php

namespace NineteenNinetyFour\Wishlist\Tests\Feature;

use Illuminate\Auth\Events\Login;
use NineteenNinetyFour\Wishlist\CurrentOwner;
use NineteenNinetyFour\Wishlist\Item;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Tests\TestCase;
use NineteenNinetyFour\Wishlist\Wishlist;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\Auth\User;

class MergeOnLoginTest extends TestCase
{
    protected const GUEST = '99999999-9999-4999-8999-999999999999';

    protected function signInAsGuestWhoBecomes(User $user): void
    {
        request()->cookies->set('wishlist_guest', self::GUEST);

        event(new Login('web', $user, false));
    }

    #[Test]
    public function a_guests_default_list_joins_the_users_and_other_lists_move_over(): void
    {
        $user = $this->user();
        $guest = Owner::guest(self::GUEST);

        $this->wishlists()->save(Wishlist::make(Owner::user($user))->setDefault()->add(Item::make('a'))->add(Item::make('b')));
        $this->wishlists()->save(Wishlist::make($guest)->setDefault()->add(Item::make('b'))->add(Item::make('c')));
        $this->wishlists()->save(Wishlist::make($guest, 'Ideas')->add(Item::make('d')));

        $this->signInAsGuestWhoBecomes($user);

        $lists = $this->wishlists()->lists(Owner::user($user));

        $this->assertSame(['My Wishlist', 'Ideas'], $lists->map->title()->all());
        $this->assertSame(['a', 'b', 'c'], $lists->first()->items()->map->reference->all());
        $this->assertCount(0, $this->wishlists()->lists($guest));
        $this->assertNull(app(CurrentOwner::class)->guestToken());
    }

    #[Test]
    public function a_user_with_no_lists_takes_the_guests(): void
    {
        $user = $this->user();
        $this->wishlists()->save(Wishlist::make(Owner::guest(self::GUEST))->setDefault()->add(Item::make('a')));

        $this->signInAsGuestWhoBecomes($user);

        $this->assertSame(['a'], $this->wishlists()->find(Owner::user($user))->items()->map->reference->all());
    }

    #[Test]
    public function merging_can_be_switched_off(): void
    {
        config(['wishlist.merge_on_login' => false]);
        $user = $this->user();
        $this->wishlists()->save(Wishlist::make(Owner::guest(self::GUEST))->setDefault()->add(Item::make('a')));

        $this->signInAsGuestWhoBecomes($user);

        $this->assertCount(0, $this->wishlists()->lists(Owner::user($user)));
        $this->assertCount(1, $this->wishlists()->lists(Owner::guest(self::GUEST)));
    }
}
