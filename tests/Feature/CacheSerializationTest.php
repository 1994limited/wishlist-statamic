<?php

namespace NineteenNinetyFour\Wishlist\Tests\Feature;

use NineteenNinetyFour\Wishlist\Item;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\ServiceProvider;
use NineteenNinetyFour\Wishlist\Tests\TestCase;
use NineteenNinetyFour\Wishlist\Wishlist;
use PHPUnit\Framework\Attributes\Test;

/**
 * Laravel 13 can restrict which classes its cache unserialises. The Stache
 * keeps the file driver's lists in the cache, so they must be allowed.
 */
class CacheSerializationTest extends TestCase
{
    #[Test]
    public function lists_come_back_out_of_a_restricted_cache_whole(): void
    {
        config(['cache.serializable_classes' => []]);
        (new ServiceProvider($this->app))->register();

        $list = Wishlist::make(Owner::user('jo'))->add(Item::make('a', ['notes' => 'Hi']))->touch();

        $copy = unserialize(serialize($list), ['allowed_classes' => config('cache.serializable_classes')]);

        $this->assertInstanceOf(Wishlist::class, $copy);
        $this->assertSame('Hi', $copy->item('a')->data['notes']);
        $this->assertSame($list->updatedAt()->toIso8601String(), $copy->updatedAt()->toIso8601String());
    }
}
