<?php

namespace NineteenNinetyFour\Wishlist\Tests\Feature;

use Carbon\CarbonImmutable;
use NineteenNinetyFour\Wishlist\Contracts\WishlistRepository;
use NineteenNinetyFour\Wishlist\Item;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Wishlist;
use PHPUnit\Framework\Attributes\Test;

/**
 * What every server driver must do. Each driver's test class uses this.
 */
trait RepositoryContract
{
    protected function repository(): WishlistRepository
    {
        return app(WishlistRepository::class);
    }

    #[Test]
    public function it_saves_and_finds_a_list_with_its_items(): void
    {
        $list = Wishlist::make(Owner::user('jo'), 'Birthday')->setDefault();
        $list->add(new Item('a1', CarbonImmutable::parse('2026-01-02 10:00:00'), ['notes' => 'Blue, please']));
        $list->add(new Item('b2', CarbonImmutable::parse('2026-01-03 10:00:00')));
        $list->touch();

        $this->repository()->save($list);

        $found = $this->repository()->find($list->id());

        $this->assertNotNull($found);
        $this->assertSame('Birthday', $found->title());
        $this->assertSame('user:jo', $found->owner());
        $this->assertTrue($found->isDefault());
        $this->assertSame($list->shareKey(), $found->shareKey());
        $this->assertSame(['a1', 'b2'], $found->items()->map->reference->all());
        $this->assertSame(['notes' => 'Blue, please'], $found->item('a1')->data);
        $this->assertSame('2026-01-02T10:00:00+00:00', $found->item('a1')->addedAt->toIso8601String());
        $this->assertNotNull($found->createdAt());
    }

    #[Test]
    public function saving_again_replaces_the_items(): void
    {
        $list = Wishlist::make(Owner::user('jo'))->add(Item::make('a1'))->add(Item::make('b2'))->touch();
        $this->repository()->save($list);

        $list->remove('a1')->add(Item::make('c3'));
        $this->repository()->save($list);

        $this->assertSame(['b2', 'c3'], $this->repository()->find($list->id())->items()->map->reference->all());
    }

    #[Test]
    public function it_finds_a_list_by_its_share_key(): void
    {
        $list = Wishlist::make(Owner::user('jo'))->touch();
        $this->repository()->save($list);

        $this->assertSame($list->id(), $this->repository()->findByShareKey($list->shareKey())?->id());
        $this->assertNull($this->repository()->findByShareKey('nope'));

        $old = $list->shareKey();
        $list->regenerateShareKey();
        $this->repository()->save($list);

        $this->assertNull($this->repository()->findByShareKey($old));
        $this->assertSame($list->id(), $this->repository()->findByShareKey($list->shareKey())?->id());
    }

    #[Test]
    public function an_owners_lists_come_default_first_then_oldest_first(): void
    {
        CarbonImmutable::setTestNow('2026-01-01');
        $this->repository()->save(Wishlist::make(Owner::user('jo'), 'Oldest')->touch());
        CarbonImmutable::setTestNow('2026-01-02');
        $this->repository()->save(Wishlist::make(Owner::user('jo'), 'Default')->setDefault()->touch());
        CarbonImmutable::setTestNow('2026-01-03');
        $this->repository()->save(Wishlist::make(Owner::user('jo'), 'Newest')->touch());
        $this->repository()->save(Wishlist::make(Owner::user('sam'), 'Not Jo\'s')->touch());
        CarbonImmutable::setTestNow();

        $this->assertSame(['Default', 'Oldest', 'Newest'], $this->repository()->forOwner(Owner::user('jo'))->map->title()->all());
        $this->assertCount(0, $this->repository()->forOwner(Owner::guest('00000000-0000-4000-8000-000000000000')));
    }

    #[Test]
    public function it_deletes_a_list(): void
    {
        $list = Wishlist::make(Owner::user('jo'))->add(Item::make('a1'))->touch();
        $this->repository()->save($list);

        $this->repository()->delete($list);

        $this->assertNull($this->repository()->find($list->id()));
        $this->assertCount(0, $this->repository()->all());
        $this->assertCount(0, $this->repository()->popular());
    }

    #[Test]
    public function it_counts_the_most_saved_references(): void
    {
        $this->repository()->save(Wishlist::make(Owner::user('a'))->add(Item::make('x'))->add(Item::make('y'))->touch());
        $this->repository()->save(Wishlist::make(Owner::user('b'))->add(Item::make('x'))->touch());
        $this->repository()->save(Wishlist::make(Owner::user('c'))->add(Item::make('x'))->add(Item::make('z'))->touch());

        $popular = $this->repository()->popular(2);

        $this->assertSame(3, $popular['x']);
        $this->assertCount(2, $popular);
        $this->assertSame('x', $popular->keys()->first());
    }

    #[Test]
    public function it_pages_and_searches_for_the_control_panel(): void
    {
        foreach (range(1, 30) as $i) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-01-01')->addMinutes($i));
            $this->repository()->save(Wishlist::make(Owner::user("user{$i}"), $i === 7 ? 'Wedding gifts' : "List {$i}")->touch());
        }
        CarbonImmutable::setTestNow();

        $first = $this->repository()->paginate(25, 1);
        $this->assertSame(30, $first->total());
        $this->assertCount(25, $first->items());
        $this->assertSame('List 30', $first->items()[0]->title());
        $this->assertCount(5, $this->repository()->paginate(25, 2)->items());

        $this->assertSame(['Wedding gifts'], collect($this->repository()->paginate(25, 1, 'wedding')->items())->map->title()->all());
        $this->assertSame(['List 12'], collect($this->repository()->paginate(25, 1, 'user:user12')->items())->map->title()->all());
    }
}
