<?php

namespace NineteenNinetyFour\Wishlist\Tests\Feature;

use DuncanMcClean\Cargo\Facades\Cart;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Blade;
use NineteenNinetyFour\Wishlist\Integrations\Cargo;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Presenter;
use NineteenNinetyFour\Wishlist\Reference;
use NineteenNinetyFour\Wishlist\ShareCodec;
use NineteenNinetyFour\Wishlist\Tests\CargoTestCase;
use NineteenNinetyFour\Wishlist\WishlistException;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Entry;

/**
 * Each test in its own process: see CargoTestCase.
 */
#[RunTestsInSeparateProcesses]
class CargoTest extends CargoTestCase
{
    protected Owner $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shirt();
        $this->mug();
        $user = $this->user();
        $this->actingAs($user);
        $this->owner = Owner::user($user);
    }

    protected function row(string $reference): array
    {
        $list = $this->wishlists()->find($this->owner);

        return collect(app(Presenter::class)->items($list->items()))->firstWhere('wishlist_item.id', $reference);
    }

    #[Test]
    public function each_variant_is_its_own_item(): void
    {
        $this->wishlists()->add($this->owner, Reference::make(self::SHIRT, 'S'));
        $this->wishlists()->add($this->owner, Reference::make(self::SHIRT, 'M'));

        $list = $this->wishlists()->find($this->owner);
        $this->assertSame([self::SHIRT.'#S', self::SHIRT.'#M'], $list->items()->map->reference->all());
        $this->assertTrue($this->wishlists()->has($this->owner, self::SHIRT.'#S'));
        $this->assertFalse($this->wishlists()->has($this->owner, self::SHIRT));
    }

    #[Test]
    public function a_variant_the_product_doesnt_have_is_refused(): void
    {
        $this->expectException(WishlistException::class);
        $this->wishlists()->add($this->owner, Reference::make(self::SHIRT, 'XXL'));
    }

    #[Test]
    public function only_products_take_a_variant(): void
    {
        $this->product('55555555-5555-4555-8555-555555555555', 'A book');
        $this->assertFalse($this->wishlists()->allowed('55555555-5555-4555-8555-555555555555#S'));
        $this->assertFalse($this->wishlists()->allowed(self::MUG.'#S'));
        $this->assertTrue($this->wishlists()->allowed(self::MUG));
    }

    #[Test]
    public function lists_show_price_stock_and_variant(): void
    {
        $this->wishlists()->add($this->owner, self::SHIRT.'#S');
        $this->wishlists()->add($this->owner, self::SHIRT.'#M');
        $this->wishlists()->add($this->owner, self::SHIRT);
        $this->wishlists()->add($this->owner, self::MUG);

        $s = $this->row(self::SHIRT.'#S')['cargo'];
        $this->assertSame(['S', 'S', 1500, '£15.00', 3, true], [$s['variant'], $s['variant_name'], $s['price'], $s['price_formatted'], $s['stock'], $s['in_stock']]);

        $this->assertFalse($this->row(self::SHIRT.'#M')['cargo']['in_stock'], 'M is sold out.');

        $unchosen = $this->row(self::SHIRT)['cargo'];
        $this->assertTrue($unchosen['needs_variant']);
        $this->assertNull($unchosen['price']);
        $this->assertFalse($unchosen['in_stock']);

        $mug = $this->row(self::MUG)['cargo'];
        $this->assertSame([800, null, true, false], [$mug['price'], $mug['stock'], $mug['in_stock'], $mug['needs_variant']]);
    }

    #[Test]
    public function the_price_when_saved_shows_a_drop(): void
    {
        $this->wishlists()->add($this->owner, self::MUG);
        Entry::find(self::MUG)->set('price', 650)->save();

        $mug = $this->row(self::MUG)['cargo'];
        $this->assertSame([800, 650, true, 150, '£1.50'], [$mug['saved_price'], $mug['price'], $mug['price_dropped'], $mug['price_drop'], $mug['price_drop_formatted']]);

        // Saving it again keeps the first price.
        $this->wishlists()->add($this->owner, self::MUG);
        $this->assertSame(800, $this->row(self::MUG)['cargo']['saved_price']);
    }

    #[Test]
    public function a_visitor_cant_set_the_saved_price(): void
    {
        $this->post('/!/wishlist/items/add', ['id' => self::MUG, 'saved_price' => 999999, 'data' => ['saved_price' => 999999]])->assertRedirect();

        $this->assertSame(800, $this->row(self::MUG)['cargo']['saved_price']);
    }

    #[Test]
    public function a_form_can_choose_the_variant(): void
    {
        $this->post('/!/wishlist/items/add', ['id' => self::SHIRT, 'variant' => 'S'])->assertRedirect();

        $this->assertTrue($this->wishlists()->has($this->owner, self::SHIRT.'#S'));
    }

    #[Test]
    public function the_tags_take_a_variant(): void
    {
        $this->wishlists()->add($this->owner, self::SHIRT.'#S');

        $html = $this->antlers('{{ wishlist:toggle id="'.self::SHIRT.'" variant="S" }}<button>Save</button>{{ /wishlist:toggle }}');
        $this->assertStringContainsString('data-wishlist-id="'.self::SHIRT.'#S"', $html);
        $this->assertStringContainsString('data-wishlist-active="true"', $html);
        $this->assertStringNotContainsString('variant="S"', $html);

        $this->assertSame('yes|no', $this->antlers('{{ if {wishlist:has id="'.self::SHIRT.'" variant="S"} }}yes{{ else }}no{{ /if }}|{{ if {wishlist:has id="'.self::SHIRT.'" variant="M"} }}yes{{ else }}no{{ /if }}'));

        $this->assertSame('£15.00 S', trim($this->antlers('{{ wishlist }}{{ items }}{{ cargo:price_formatted }} {{ cargo:variant_name }}{{ /items }}{{ /wishlist }}')));
        $this->assertSame('£15.00 S', trim(Blade::render('<s:wishlist>@foreach ($items as $item){{ $item[\'cargo\'][\'price_formatted\'] }} {{ $item[\'cargo\'][\'variant_name\'] }}@endforeach</s:wishlist>')));
    }

    #[Test]
    public function the_list_goes_in_the_basket_without_whats_out_of_stock_or_unchosen(): void
    {
        $this->wishlists()->add($this->owner, self::SHIRT.'#S', null, ['quantity' => 2]);
        $this->wishlists()->add($this->owner, self::SHIRT.'#M');
        $this->wishlists()->add($this->owner, self::SHIRT);
        $this->wishlists()->add($this->owner, self::MUG);

        $this->postJson('/!/wishlist/cart')
            ->assertOk()
            ->assertJsonPath('cart.added', 2)
            ->assertJsonPath('message', 'Added 2 to your basket. 2 couldn’t be added: out of stock, or a size, option or amount still to choose.');

        // The basket the request made: the only one there is.
        $cart = Cart::query()->get()->sole();
        $lines = $cart->lineItems()->map(fn ($line) => [$line->product()->id(), $line->variant()?->key(), $line->quantity()])->values()->all();
        $this->assertSame([[self::SHIRT, 'S', 2], [self::MUG, null, 1]], $lines);
    }

    #[Test]
    public function the_add_to_cart_tag_is_a_form_to_it(): void
    {
        $html = $this->antlers('{{ wishlist:add_to_cart }}<button>Add all</button>{{ /wishlist:add_to_cart }}');
        $this->assertStringContainsString('action="http://localhost/!/wishlist/cart"', $html);
        $this->assertStringContainsString('<button>Add all</button>', $html);
    }

    #[Test]
    public function with_the_browser_driver_the_list_comes_with_the_form(): void
    {
        config(['wishlist.driver' => 'browser']);
        $this->withoutMiddleware(PreventRequestForgery::class);

        $i = ShareCodec::encode([self::SHIRT.'#S', self::MUG, self::SHIRT.'#XXL', 'not-a-product']);

        $this->postJson('/!/wishlist/cart', ['i' => $i])
            ->assertOk()
            ->assertJsonPath('cart.added', 2)
            ->assertJsonPath('message', 'Added 2 items to your basket.');

        $this->assertSame([self::SHIRT, self::MUG], Cart::query()->get()->sole()->lineItems()->map(fn ($line) => $line->product()->id())->values()->all());
    }

    #[Test]
    public function without_cargo_theres_no_basket(): void
    {
        $this->app->instance(Cargo::class, new class extends Cargo
        {
            public function installed(): bool
            {
                return false;
            }
        });

        $this->postJson('/!/wishlist/cart')->assertNotFound();
        $this->assertSame('', $this->antlers('{{ wishlist:add_to_cart }}<button>Add all</button>{{ /wishlist:add_to_cart }}'));
        $this->assertFalse($this->wishlists()->allowed(self::SHIRT.'#S'));
    }

    #[Test]
    public function a_voucher_of_any_amount_needs_its_amount_chosen(): void
    {
        // As Gift Vouchers (1994/gift-vouchers-statamic) makes them.
        Entry::make()->collection('products')->blueprint('product')->id('66666666-6666-4666-8666-666666666666')->slug('voucher')
            ->data(['title' => 'Gift voucher', 'voucher_amount_type' => 'open', 'voucher_min' => 1000, 'voucher_max' => 20000])->save();

        $this->wishlists()->add($this->owner, '66666666-6666-4666-8666-666666666666');
        $this->wishlists()->add($this->owner, self::MUG);

        $voucher = $this->row('66666666-6666-4666-8666-666666666666')['cargo'];
        $this->assertSame([true, true, false, null, null, false], [$voucher['needs_amount'], $voucher['needs_choice'], $voucher['needs_variant'], $voucher['price'], $voucher['saved_price'], $voucher['in_stock']]);

        $this->postJson('/!/wishlist/cart')->assertOk()->assertJsonPath('cart.added', 1)->assertJsonPath('cart.skipped', ['66666666-6666-4666-8666-666666666666']);
    }
}
