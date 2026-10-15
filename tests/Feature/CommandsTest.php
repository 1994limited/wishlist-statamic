<?php

namespace NineteenNinetyFour\Wishlist\Tests\Feature;

use Carbon\CarbonImmutable;
use NineteenNinetyFour\Wishlist\Database\EloquentWishlistRepository;
use NineteenNinetyFour\Wishlist\Item;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Stache\StacheWishlistRepository;
use NineteenNinetyFour\Wishlist\Tests\TestCase;
use NineteenNinetyFour\Wishlist\Wishlist;
use PHPUnit\Framework\Attributes\Test;

class CommandsTest extends TestCase
{
    #[Test]
    public function lists_move_from_files_to_the_database_keeping_ids_keys_and_dates(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        CarbonImmutable::setTestNow('2026-03-01 09:00:00');
        $list = Wishlist::make(Owner::user('jo'), 'Birthday')->setDefault()->add(Item::make('a', ['notes' => 'Hi']));
        $this->wishlists()->save($list);
        CarbonImmutable::setTestNow('2026-06-01');

        $this->artisan('wishlist:transfer', ['--from' => 'file', '--to' => 'database', '--force' => true])
            ->expectsOutputToContain('Copied 1 wishlists to the database driver.')
            ->assertSuccessful();

        $copy = app(EloquentWishlistRepository::class)->findByShareKey($list->shareKey());

        $this->assertSame($list->id(), $copy->id());
        $this->assertSame('Birthday', $copy->title());
        $this->assertSame(['notes' => 'Hi'], $copy->item('a')->data);
        $this->assertSame('2026-03-01', $copy->updatedAt()->toDateString());
        CarbonImmutable::setTestNow();
    }

    #[Test]
    public function transfer_says_to_migrate_when_the_tables_do_not_exist(): void
    {
        $this->wishlists()->save(Wishlist::make(Owner::user('jo')));

        $this->artisan('wishlist:transfer', ['--from' => 'file', '--to' => 'database', '--force' => true])
            ->expectsOutputToContain('The wishlist tables don\'t exist yet.')
            ->expectsOutputToContain('php please vendor:publish --tag=wishlist-migrations')
            ->expectsOutputToContain('php artisan migrate')
            ->assertFailed();
    }

    #[Test]
    public function transfer_refuses_the_browser_driver(): void
    {
        $this->artisan('wishlist:transfer', ['--from' => 'browser', '--to' => 'file'])->assertFailed();
    }

    #[Test]
    public function prune_deletes_old_guest_lists_only(): void
    {
        CarbonImmutable::setTestNow('2026-01-01');
        $this->wishlists()->save($old = Wishlist::make(Owner::guest('99999999-9999-4999-8999-999999999999')));
        $this->wishlists()->save($user = Wishlist::make(Owner::user('jo')));
        CarbonImmutable::setTestNow('2026-05-01');
        $this->wishlists()->save($recent = Wishlist::make(Owner::guest('88888888-8888-4888-8888-888888888888')));

        $this->artisan('wishlist:prune', ['--days' => 30])->expectsOutputToContain('Deleted 1 guest wishlists')->assertSuccessful();

        $repository = app(StacheWishlistRepository::class);
        $this->assertNull($repository->find($old->id()));
        $this->assertNotNull($repository->find($user->id()));
        $this->assertNotNull($repository->find($recent->id()));
        CarbonImmutable::setTestNow();
    }
}
