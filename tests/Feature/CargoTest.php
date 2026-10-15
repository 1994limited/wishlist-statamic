<?php

namespace NineteenNinetyFour\Wishlist\Tests\Feature;

use DuncanMcClean\Cargo\Facades\Cart;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Blade;
use NineteenNinetyFour\Wishlist\Integrations\Cargo;
use NineteenNinetyFour\Wishlist\Item;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Presenter;
use NineteenNinetyFour\Wishlist\Reference;
use NineteenNinetyFour\Wishlist\ShareCodec;
use NineteenNinetyFour\Wishlist\Tests\CargoTestCase;
use NineteenNinetyFour\Wishlist\WishlistException;
use NineteenNinetyFour\Wishlist\Wishlists;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;

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
    public function a_button_for_each_variant_from_the_products_options(): void
    {
        $this->wishlists()->add($this->owner, self::SHIRT.'#M');
        $product = Entry::find(self::SHIRT)->toAugmentedArray();

        $antlers = $this->antlers('{{ product_variants:options }}{{ wishlist:toggle :id="id" :variant="key" }}<button>{{ variant }}</button>{{ /wishlist:toggle }}{{ /product_variants:options }}', $product);
        $this->assertStringContainsString('data-wishlist-id="'.self::SHIRT.'#S" data-wishlist-active="false"', $antlers);
        $this->assertStringContainsString('data-wishlist-id="'.self::SHIRT.'#M" data-wishlist-active="true"', $antlers);

        $blade = Blade::render('@foreach ($product_variants[\'options\'] as $option)<s:wishlist:toggle :id="$id" :variant="$option[\'key\']"><button>{{ $option[\'variant\'] }}</button></s:wishlist:toggle>@endforeach', $product);
        $this->assertStringContainsString('data-wishlist-id="'.self::SHIRT.'#S" data-wishlist-active="false"', $blade);
        $this->assertStringContainsString('data-wishlist-id="'.self::SHIRT.'#M" data-wishlist-active="true"', $blade);
    }

    #[Test]
    public function a_variant_is_removed_by_its_item_id(): void
    {
        $this->wishlists()->add($this->owner, self::SHIRT.'#S');
        $this->wishlists()->add($this->owner, self::SHIRT.'#M');

        $html = $this->antlers('{{ wishlist }}{{ items }}<button {{ wishlist:toggle_attrs :id="wishlist_item:id" }}>Remove</button>{{ /items }}{{ /wishlist }}');
        $this->assertStringContainsString('data-wishlist-toggle="'.self::SHIRT.'#S"', $html);
        $this->assertStringContainsString('data-wishlist-toggle="'.self::SHIRT.'#M"', $html);

        $this->post('/!/wishlist/items/remove', ['id' => self::SHIRT, 'variant' => 'S'])->assertRedirect();
        $this->assertFalse($this->wishlists()->has($this->owner, self::SHIRT.'#S'));
        $this->assertTrue($this->wishlists()->has($this->owner, self::SHIRT.'#M'));
    }

    #[Test]
    public function a_list_partial_with_add_to_basket_for_each_product(): void
    {
        $this->wishlists()->add($this->owner, self::SHIRT.'#S');
        $this->wishlists()->add($this->owner, self::SHIRT.'#M');
        $this->wishlists()->add($this->owner, self::SHIRT);
        $this->wishlists()->add($this->owner, self::MUG);

        $html = $this->antlers('{{ wishlist }}{{ items }}[{{ title }}{{ if cargo:needs_choice }} choose{{ else }} {{ cargo:price_formatted }}{{ if cargo:in_stock }}{{ cart:add }}<input type="hidden" name="product" value="{{ cargo:product }}">{{ if cargo:variant }}<input type="hidden" name="variant" value="{{ cargo:variant }}">{{ /if }}<button>Add</button>{{ /cart:add }}{{ else }} sold out{{ /if }}{{ /if }}]{{ /items }}{{ /wishlist }}');

        $this->assertStringContainsString('name="variant" value="S"', $html);
        $this->assertStringContainsString(' £17.00 sold out]', $html);
        $this->assertStringContainsString('[Linen Shirt choose]', $html);
        $this->assertSame(2, substr_count($html, 'cargo/cart/line-items'));
        $this->assertSame('ppcp', $this->antlers('{{ wishlist }}{{ items }}{{ if cargo:needs_choice }}c{{ elseif cargo }}p{{ else }}n{{ /if }}{{ /items }}{{ /wishlist }}'));
        $this->assertStringContainsString('name="product" value="'.self::MUG.'"', $html);
    }

    #[Test]
    public function the_basket_form_takes_a_list_and_a_redirect(): void
    {
        $this->wishlists()->add($this->owner, self::MUG);
        $other = $this->wishlists()->create($this->owner, 'Other');

        $html = $this->antlers('{{ wishlist:add_to_cart list="'.$other->id().'" redirect="/checkout" }}<button>Add all</button>{{ /wishlist:add_to_cart }}');
        $this->assertStringContainsString('name="list" value="'.$other->id().'"', $html);
        $this->assertStringContainsString('name="_redirect" value="/checkout"', $html);

        $this->post('/!/wishlist/cart', ['list' => $other->id(), '_redirect' => '/checkout'])
            ->assertRedirect('/checkout')
            ->assertSessionHas('wishlist_success', 'Nothing on this list could go in your basket.');
    }

    #[Test]
    public function only_supported_cargo_versions_are_used(): void
    {
        $cargo = app(Cargo::class);

        $this->assertTrue($cargo->supported('1.15.0.0'));
        $this->assertTrue($cargo->supported('1.22.3.0'));
        $this->assertTrue($cargo->supported('dev-main'));
        $this->assertFalse($cargo->supported('1.14.3.0'));
        $this->assertFalse($cargo->supported('2.0.0.0'));
        $this->assertTrue($cargo->installed());
    }

    #[Test]
    public function with_an_unsupported_cargo_saved_variants_stay_on_the_list(): void
    {
        $this->wishlists()->add($this->owner, self::SHIRT.'#S');

        $this->app->instance(Cargo::class, new class extends Cargo
        {
            public function supported(?string $version = null): bool
            {
                return false;
            }
        });

        $this->assertSame('1', $this->antlers('{{ wishlist:count }}'));
        $this->assertSame('', $this->antlers('{{ wishlist:add_to_cart }}<button>Add</button>{{ /wishlist:add_to_cart }}'));
        $this->assertFalse($this->wishlists()->allowed(self::SHIRT.'#M'));
    }

    #[Test]
    public function a_price_saved_without_its_currency_counts_only_with_one_currency(): void
    {
        $list = $this->wishlists()->create($this->owner, 'Mine');
        $list->add(Item::make(self::MUG, ['saved_price' => 1000]));
        $this->wishlists()->save($list);

        $this->assertTrue($this->row(self::MUG)['cargo']['price_dropped']);

        Site::setSites([
            'default' => ['name' => 'UK', 'url' => '/', 'locale' => 'en_GB', 'attributes' => ['currency' => 'GBP']],
            'us' => ['name' => 'US', 'url' => '/us/', 'locale' => 'en_US', 'attributes' => ['currency' => 'USD']],
        ]);

        $this->assertFalse($this->row(self::MUG)['cargo']['price_dropped']);
    }

    #[Test]
    public function an_unusable_cookie_name_falls_back(): void
    {
        config(['wishlist.picked_cookie' => 'site.wishlist; path=/']);
        $this->assertSame('wishlist_picked', Wishlists::pickedCookie());

        config(['wishlist.picked_cookie' => 'shop_two-picked']);
        $this->assertSame('shop_two-picked', Wishlists::pickedCookie());
    }

    #[Test]
    public function what_cant_be_saved_any_more_doesnt_go_in_the_basket(): void
    {
        $this->wishlists()->add($this->owner, self::MUG);
        Entry::find(self::MUG)->published(false)->save();

        $this->postJson('/!/wishlist/cart')->assertOk()->assertJsonPath('cart.added', 0);
        $this->assertSame(0, Cart::query()->get()->sum(fn ($cart) => $cart->lineItems()->count()));
    }

    #[Test]
    public function a_variant_the_product_no_longer_has_drops_off_the_list(): void
    {
        $this->wishlists()->add($this->owner, self::SHIRT.'#M');
        $this->wishlists()->add($this->owner, self::SHIRT.'#S');

        $shirt = Entry::find(self::SHIRT);
        $variants = $shirt->get('product_variants');
        $variants['options'] = [$variants['options'][0]];
        $shirt->set('product_variants', $variants)->save();

        $this->assertSame('1', $this->antlers('{{ wishlist:count }}'));
        $this->assertSame([self::SHIRT.'#S'], collect(app(Presenter::class)->items($this->wishlists()->find($this->owner)->items()))->pluck('wishlist_item.id')->all());
    }

    #[Test]
    public function the_saved_price_stays_the_servers_even_as_an_item_field(): void
    {
        config(['wishlist.item_fields' => ['notes', 'quantity', 'saved_price', 'saved_currency']]);

        $this->post('/!/wishlist/items/add', ['id' => self::MUG, 'saved_price' => 99999, 'saved_currency' => 'XXX'])->assertRedirect();
        $this->post('/!/wishlist/items/add', ['id' => self::MUG, 'saved_price' => 99999])->assertRedirect();

        $data = $this->wishlists()->find($this->owner)->item(self::MUG)->data;
        $this->assertSame(800, $data['saved_price']);
        $this->assertSame('GBP', $data['saved_currency']);
    }

    #[Test]
    public function a_price_saved_in_another_currency_shows_no_drop(): void
    {
        $this->wishlists()->add($this->owner, self::MUG);
        $list = $this->wishlists()->find($this->owner);
        $list->add(Item::make(self::MUG, ['saved_price' => 2000, 'saved_currency' => 'USD']));
        $this->wishlists()->save($list);

        $row = $this->row(self::MUG);
        $this->assertNull($row['cargo']['saved_price']);
        $this->assertFalse($row['cargo']['price_dropped']);
    }

    #[Test]
    public function with_the_browser_driver_quantities_come_with_the_form(): void
    {
        config(['wishlist.driver' => 'browser']);
        $this->withoutMiddleware(PreventRequestForgery::class);

        $items = json_encode([['id' => self::MUG, 'data' => ['quantity' => 3]], ['id' => self::SHIRT.'#S', 'data' => ['quantity' => 'lots']], ['id' => 'nope']]);

        $this->postJson('/!/wishlist/cart', ['items' => $items])->assertOk()->assertJsonPath('cart.added', 2);

        $lines = Cart::query()->get()->sole()->lineItems()->map(fn ($line) => [$line->product()->id(), $line->quantity()])->values()->all();
        $this->assertSame([[self::MUG, 3], [self::SHIRT, 1]], $lines);
    }

    #[Test]
    public function the_basket_form_uses_the_picked_list(): void
    {
        $this->wishlists()->add($this->owner, self::SHIRT.'#S');
        $other = $this->wishlists()->create($this->owner, 'Other');
        $this->wishlists()->add($this->owner, self::MUG, $other->id());

        $this->assertStringNotContainsString('name="list"', $this->antlers('{{ wishlist:add_to_cart }}<button>Add</button>{{ /wishlist:add_to_cart }}'));

        $this->withCredentials()
            ->withUnencryptedCookie(Wishlists::pickedCookie(), $other->id())
            ->postJson('/!/wishlist/cart')
            ->assertOk()
            ->assertJsonPath('cart.added', 1);

        $this->assertSame([self::MUG], Cart::query()->get()->sole()->lineItems()->map(fn ($line) => $line->product()->id())->values()->all());
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
            ->assertJsonPath('message', 'Added 2 to your basket. 2 couldn’t be added: out of stock, no longer available, or with a size, option or amount still to choose.');

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
