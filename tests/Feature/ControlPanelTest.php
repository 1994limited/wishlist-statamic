<?php

namespace NineteenNinetyFour\Wishlist\Tests\Feature;

use Inertia\Testing\AssertableInertia;
use NineteenNinetyFour\Wishlist\Item;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Tests\TestCase;
use NineteenNinetyFour\Wishlist\Wishlist;
use PHPUnit\Framework\Attributes\Test;

class ControlPanelTest extends TestCase
{
    protected const A = '11111111-1111-4111-8111-111111111111';

    protected Wishlist $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product(self::A, 'Linen Shirt');
        $this->user('jo@example.com');

        $this->list = Wishlist::make(Owner::user('jo'), 'Birthday')->setDefault()->add(Item::make(self::A, ['quantity' => 2]))->add(Item::make('gone'));
        $this->wishlists()->save($this->list);
        $this->wishlists()->save(Wishlist::make(Owner::guest('99999999-9999-4999-8999-999999999999'))->add(Item::make(self::A)));
    }

    #[Test]
    public function it_needs_permission(): void
    {
        $this->actingAs($this->user('editor@example.com', ['access cp']))
            ->getJson(cp_route('wishlist.index'))
            ->assertForbidden();
    }

    #[Test]
    public function it_lists_every_wishlist_and_the_most_saved(): void
    {
        $this->actingAs($this->user('admin@example.com', ['view wishlists']))
            ->get(cp_route('wishlist.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('wishlist::Index')
                ->where('storesOnServer', true)
                ->has('lists', 2)
                ->where('pagination.total', 2)
                ->where('popular.0.title', 'Linen Shirt')
                ->where('popular.0.count', 2)
                ->etc());
    }

    #[Test]
    public function it_searches(): void
    {
        $this->actingAs($this->user('admin@example.com', ['view wishlists']))
            ->get(cp_route('wishlist.index', ['search' => 'birth']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('lists', 1)
                ->where('lists.0.title', 'Birthday')
                ->where('lists.0.owner.name', 'jo@example.com')
                ->etc());
    }

    #[Test]
    public function it_shows_a_list(): void
    {
        $this->actingAs($this->user('admin@example.com', ['view wishlists']))
            ->get(cp_route('wishlist.show', $this->list->id()))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('wishlist::Show')
                ->where('list.title', 'Birthday')
                ->where('list.share_url', 'http://localhost/wishlist/shared/'.$this->list->shareKey())
                ->where('items.0.title', 'Linen Shirt')
                ->where('items.0.kind', 'Products')
                ->where('items.0.data.quantity', 2)
                ->where('items.1.missing', true)
                ->where('canDelete', false)
                ->etc());
    }

    #[Test]
    public function deleting_needs_its_own_permission(): void
    {
        $this->actingAs($this->user('viewer@example.com', ['view wishlists']))
            ->deleteJson(cp_route('wishlist.destroy', $this->list->id()))
            ->assertForbidden();

        $this->assertNotNull($this->wishlists()->repository()->find($this->list->id()));

        $this->actingAs($this->user('admin@example.com', ['view wishlists', 'delete wishlists']))
            ->delete(cp_route('wishlist.destroy', $this->list->id()))
            ->assertRedirect(cp_route('wishlist.index'));

        $this->assertNull($this->wishlists()->repository()->find($this->list->id()));
    }
}
