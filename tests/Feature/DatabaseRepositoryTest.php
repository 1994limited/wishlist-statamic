<?php

namespace NineteenNinetyFour\Wishlist\Tests\Feature;

use Illuminate\Support\Facades\DB;
use NineteenNinetyFour\Wishlist\Database\EloquentWishlistRepository;
use NineteenNinetyFour\Wishlist\Item;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Tests\TestCase;
use NineteenNinetyFour\Wishlist\Wishlist;
use PHPUnit\Framework\Attributes\Test;

class DatabaseRepositoryTest extends TestCase
{
    use RepositoryContract;

    protected string $driver = 'database';

    #[Test]
    public function the_database_driver_uses_eloquent(): void
    {
        $this->assertInstanceOf(EloquentWishlistRepository::class, $this->repository());
    }

    #[Test]
    public function items_are_rows_of_their_own(): void
    {
        $list = Wishlist::make(Owner::user('jo'))->add(Item::make('a1', ['notes' => 'Hi']))->add(Item::make('b2'))->touch();
        $this->repository()->save($list);

        $this->assertSame(1, DB::table('wishlists')->count());
        $this->assertSame(['a1', 'b2'], DB::table('wishlist_items')->orderBy('id')->pluck('reference')->all());
        $this->assertSame(['notes' => 'Hi'], json_decode(DB::table('wishlist_items')->where('reference', 'a1')->value('data'), true));
    }
}
