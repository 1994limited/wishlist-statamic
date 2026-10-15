<?php

namespace NineteenNinetyFour\Wishlist\Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use NineteenNinetyFour\Wishlist\Contracts\WishlistRepository;
use NineteenNinetyFour\Wishlist\Events\ItemAdded;
use NineteenNinetyFour\Wishlist\Events\ItemRemoved;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\ShareCodec;
use NineteenNinetyFour\Wishlist\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The /!/wishlist actions, as a visitor uses them. Runs with the file
 * driver here and the database driver in DatabaseActionsTest.
 */
class ActionsTest extends TestCase
{
    protected const A = '11111111-1111-4111-8111-111111111111';

    protected const B = '22222222-2222-4222-8222-222222222222';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->product(self::A, 'Linen Shirt');
        $this->product(self::B, 'Wool Coat');
    }

    protected function guestCookie(TestResponse $response): string
    {
        $cookie = $response->getCookie('wishlist_guest');
        $this->assertNotNull($cookie, 'No guest cookie was set.');

        return $cookie->getValue();
    }

    #[Test]
    public function a_guest_adds_an_item_and_gets_a_cookie_and_a_default_list(): void
    {
        Event::fake([ItemAdded::class]);

        $response = $this->postJson('/!/wishlist/items/add', ['id' => self::A])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('added', true)
            ->assertJsonPath('list.title', 'My Wishlist')
            ->assertJsonPath('list.default', true)
            ->assertJsonPath('list.items.0.id', self::A);

        $token = $this->guestCookie($response);
        $lists = app(WishlistRepository::class)->forOwner(Owner::guest($token));

        $this->assertCount(1, $lists);
        $this->assertTrue($lists->first()->has(self::A));
        Event::assertDispatched(ItemAdded::class, fn (ItemAdded $event) => $event->item->reference === self::A);
    }

    #[Test]
    public function the_guest_keeps_adding_to_the_same_list(): void
    {
        $token = $this->guestCookie($this->postJson('/!/wishlist/items/add', ['id' => self::A]));

        $this->withCookie('wishlist_guest', $token)
            ->postJson('/!/wishlist/items/add', ['id' => self::B])
            ->assertJsonCount(1, 'lists')
            ->assertJsonCount(2, 'list.items');
    }

    #[Test]
    public function a_signed_in_user_keeps_lists_on_their_account(): void
    {
        $user = $this->user();

        $this->actingAs($user)->postJson('/!/wishlist/items/add', ['id' => self::A])->assertOk();

        $this->assertTrue($this->wishlists()->has(Owner::user($user), self::A));
    }

    #[Test]
    public function toggle_adds_then_removes(): void
    {
        Event::fake([ItemRemoved::class]);
        $user = $this->user();

        $this->actingAs($user)->postJson('/!/wishlist/items/toggle', ['id' => self::A])->assertJsonPath('added', true);
        $this->actingAs($user)->postJson('/!/wishlist/items/toggle', ['id' => self::A])->assertJsonPath('added', false)->assertJsonCount(0, 'list.items');

        Event::assertDispatched(ItemRemoved::class);
    }

    #[Test]
    public function only_published_entries_in_allowed_collections_can_be_added(): void
    {
        $this->product('33333333-3333-4333-8333-333333333333', 'Draft', published: false);
        $this->page('44444444-4444-4444-8444-444444444444', 'About');
        $user = $this->user();

        $this->actingAs($user)->postJson('/!/wishlist/items/add', ['id' => '33333333-3333-4333-8333-333333333333'])->assertStatus(422)->assertJsonPath('success', false);
        $this->actingAs($user)->postJson('/!/wishlist/items/add', ['id' => 'not-a-thing'])->assertStatus(422);
        $this->actingAs($user)->postJson('/!/wishlist/items/add', ['id' => '44444444-4444-4444-8444-444444444444'])->assertOk();

        config(['wishlist.collections' => ['products']]);

        $this->actingAs($user)->postJson('/!/wishlist/items/add', ['id' => '44444444-4444-4444-8444-444444444444'])->assertStatus(422);
        $this->actingAs($user)->postJson('/!/wishlist/items/add', ['id' => self::A])->assertOk();
    }

    #[Test]
    public function terms_can_be_added_when_their_taxonomy_is_allowed(): void
    {
        $term = $this->tag('linen');
        $user = $this->user();

        $this->actingAs($user)->postJson('/!/wishlist/items/add', ['id' => $term->id()])->assertStatus(422);

        config(['wishlist.taxonomies' => ['tags']]);

        $this->actingAs($user)->postJson('/!/wishlist/items/add', ['id' => $term->id()])->assertOk()->assertJsonPath('list.items.0.id', 'tags::linen');
    }

    #[Test]
    public function only_the_configured_item_fields_are_kept(): void
    {
        $user = $this->user();

        $this->actingAs($user)->postJson('/!/wishlist/items/add', [
            'id' => self::A,
            'notes' => '<b>Size M</b>',
            'data' => ['quantity' => '3', 'price' => '0.01'],
        ])->assertJsonPath('list.items.0.data', ['notes' => 'Size M', 'quantity' => 3]);
    }

    #[Test]
    public function lists_and_items_are_limited(): void
    {
        config(['wishlist.limits' => ['lists' => 2, 'items' => 1]]);
        $user = $this->user();

        $this->actingAs($user)->postJson('/!/wishlist/items/add', ['id' => self::A])->assertOk();
        $this->actingAs($user)->postJson('/!/wishlist/items/add', ['id' => self::A])->assertOk();
        $this->actingAs($user)->postJson('/!/wishlist/items/add', ['id' => self::B])->assertStatus(422)->assertJsonPath('message', 'A wishlist can hold up to 1 items.');

        $this->actingAs($user)->postJson('/!/wishlist/lists/create', ['title' => 'Second'])->assertOk();
        $this->actingAs($user)->postJson('/!/wishlist/lists/create', ['title' => 'Third'])->assertStatus(422);
    }

    #[Test]
    public function a_visitor_manages_several_lists(): void
    {
        $user = $this->user();
        $owner = Owner::user($user);

        $first = $this->actingAs($user)->postJson('/!/wishlist/lists/create', ['title' => 'Holiday'])->assertJsonPath('list.default', true)->json('list.id');
        $second = $this->actingAs($user)->postJson('/!/wishlist/lists/create', ['title' => '  <i>Gifts</i> '])->assertJsonPath('list.title', 'Gifts')->assertJsonPath('list.default', false)->json('list.id');

        $this->actingAs($user)->postJson('/!/wishlist/items/add', ['id' => self::A, 'list' => $second])->assertOk();
        $this->assertFalse($this->wishlists()->has($owner, self::A));
        $this->assertTrue($this->wishlists()->has($owner, self::A, $second));
        $this->assertTrue($this->wishlists()->has($owner, self::A, 'any'));

        $this->actingAs($user)->postJson('/!/wishlist/lists/rename', ['list' => $first, 'title' => 'Summer'])->assertJsonPath('list.title', 'Summer');

        // Deleting the default hands it to the next list.
        $this->actingAs($user)->postJson('/!/wishlist/lists/delete', ['list' => $first])->assertOk()->assertJsonCount(1, 'lists');
        $this->assertSame($second, $this->wishlists()->find($owner)->id());
        $this->assertTrue($this->wishlists()->find($owner)->isDefault());

        $this->actingAs($user)->postJson('/!/wishlist/lists/clear', ['list' => $second])->assertJsonCount(0, 'list.items');
    }

    #[Test]
    public function nobody_can_touch_someone_elses_list(): void
    {
        $owner = $this->user('owner@example.com');
        $list = $this->actingAs($owner)->postJson('/!/wishlist/items/add', ['id' => self::A])->json('list.id');

        $intruder = $this->user('intruder@example.com');

        foreach (['items/remove' => ['id' => self::A], 'items/add' => ['id' => self::B], 'lists/delete' => [], 'lists/clear' => [], 'lists/rename' => ['title' => 'Mine']] as $action => $payload) {
            $this->actingAs($intruder)->postJson("/!/wishlist/{$action}", $payload + ['list' => $list])->assertNotFound();
        }

        $this->assertSame([self::A], $this->wishlists()->find(Owner::user($owner))->items()->map->reference->all());
    }

    #[Test]
    public function a_new_share_link_replaces_the_old_one(): void
    {
        $user = $this->user();
        $old = $this->actingAs($user)->postJson('/!/wishlist/items/add', ['id' => self::A])->json('list.share_url');

        $new = $this->actingAs($user)->postJson('/!/wishlist/lists/share')->json('list.share_url');

        $this->assertNotSame($old, $new);
        $this->get(parse_url($old, PHP_URL_PATH))->assertNotFound();
        $this->get(parse_url($new, PHP_URL_PATH))->assertOk();
    }

    #[Test]
    public function a_shared_list_can_be_copied_by_key_or_by_encoded_items(): void
    {
        $owner = $this->user('owner@example.com');
        $this->actingAs($owner)->postJson('/!/wishlist/items/add', ['id' => self::A]);
        $key = $this->wishlists()->find(Owner::user($owner))->shareKey();

        $friend = $this->user('friend@example.com');

        $this->actingAs($friend)->postJson('/!/wishlist/lists/import', ['key' => $key])->assertJsonPath('imported', 1);
        $this->actingAs($friend)->postJson('/!/wishlist/lists/import', ['i' => ShareCodec::encode([self::A, self::B, 'not-a-thing'])])->assertJsonPath('imported', 1);

        $this->assertSame([self::A, self::B], $this->wishlists()->find(Owner::user($friend))->items()->map->reference->all());
        $this->actingAs($friend)->postJson('/!/wishlist/lists/import', ['key' => 'nope'])->assertNotFound();
    }

    #[Test]
    public function a_form_post_redirects_back_with_a_message(): void
    {
        $user = $this->user();

        $this->actingAs($user)->from('/products/linen-shirt')
            ->post('/!/wishlist/items/add', ['id' => self::A])
            ->assertRedirect('/products/linen-shirt')
            ->assertSessionHas('wishlist_success', 'Added to My Wishlist.');

        $this->actingAs($user)->from('/products/linen-shirt')
            ->post('/!/wishlist/items/add', ['id' => 'nothing'])
            ->assertRedirect('/products/linen-shirt')
            ->assertSessionHasErrors(['wishlist'], null, 'wishlist');
    }

    #[Test]
    public function a_form_can_redirect_on_this_site_but_not_elsewhere(): void
    {
        $user = $this->user();

        $this->actingAs($user)->from('/here')->post('/!/wishlist/items/add', ['id' => self::A, '_redirect' => '/wishlist'])->assertRedirect('/wishlist');
        $this->actingAs($user)->from('/here')->post('/!/wishlist/items/add', ['id' => self::A, '_redirect' => 'https://evil.example/'])->assertRedirect('/here');
        $this->actingAs($user)->from('/here')->post('/!/wishlist/items/add', ['id' => self::A, '_redirect' => '//evil.example/'])->assertRedirect('/here');
    }

    #[Test]
    public function guests_can_be_turned_off(): void
    {
        config(['wishlist.guests' => false]);

        $this->postJson('/!/wishlist/items/add', ['id' => self::A])->assertStatus(401)->assertJsonPath('message', 'Sign in to keep a wishlist.');
    }

    #[Test]
    public function state_gives_the_script_the_lists_and_a_csrf_token(): void
    {
        $user = $this->user();
        $this->actingAs($user)->postJson('/!/wishlist/items/add', ['id' => self::A]);

        $this->actingAs($user)->getJson('/!/wishlist/state')
            ->assertOk()
            ->assertJsonPath('driver', $this->driver)
            ->assertJsonPath('signed_in', true)
            ->assertJsonPath('lists.0.items.0.id', self::A)
            ->assertJsonStructure(['csrf']);
    }

    #[Test]
    public function state_does_not_set_a_cookie_for_a_guest_who_has_saved_nothing(): void
    {
        $response = $this->getJson('/!/wishlist/state')->assertOk()->assertJsonPath('lists', []);

        $this->assertNull($response->getCookie('wishlist_guest', false));
    }

    #[Test]
    public function resolve_gives_fields_of_allowed_references_only(): void
    {
        $this->product('33333333-3333-4333-8333-333333333333', 'Draft', published: false);

        $this->getJson('/!/wishlist/resolve?i='.ShareCodec::encode([self::B, '33333333-3333-4333-8333-333333333333', self::A]))
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.id', self::B)
            ->assertJsonPath('items.0.title', 'Wool Coat')
            ->assertJsonPath('items.0.url', '/products/wool-coat')
            ->assertJsonPath('items.1.title', 'Linen Shirt');
    }

    #[Test]
    public function the_script_is_served(): void
    {
        $this->get('/!/wishlist/wishlist.js')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
            ->assertSee('window.Wishlist = Wishlist', false);
    }
}
