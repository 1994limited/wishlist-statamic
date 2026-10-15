<?php

namespace NineteenNinetyFour\Wishlist\Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Crypt;
use NineteenNinetyFour\Wishlist\Contracts\WishlistRepository;
use NineteenNinetyFour\Wishlist\Item;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\ShareCodec;
use NineteenNinetyFour\Wishlist\Tests\TestCase;
use NineteenNinetyFour\Wishlist\Wishlist;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Stache;

/**
 * Whatever a visitor sends, or an edited file holds, nothing breaks and
 * nothing they wrote runs on someone else's page.
 */
class SafetyTest extends TestCase
{
    protected const A = '11111111-1111-4111-8111-111111111111';

    protected const XSS = '"><script>alert(1)</script>';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->product(self::A, 'Linen Shirt');
        file_put_contents(__DIR__.'/../__fixtures__/views/partials/probe.antlers.html', '<h2 title="{{ title }}">{{ title }}</h2>{{ items }}<p data-note="{{ wishlist_item:notes }}">{{ wishlist_item:notes }}</p>{{ /items }}');
    }

    protected function tearDown(): void
    {
        @unlink(__DIR__.'/../__fixtures__/views/partials/probe.antlers.html');

        parent::tearDown();
    }

    #[Test]
    public function a_title_in_a_link_is_escaped_where_the_list_is_drawn(): void
    {
        $html = $this->get('/!/wishlist/render?view='.urlencode(Crypt::encryptString('partials/probe')).'&t='.urlencode(self::XSS))->getContent();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('title="">', $html);
        $this->assertStringContainsString('&quot;&gt;alert(1)', $html);
    }

    #[Test]
    public function a_title_in_a_share_link_is_escaped_on_the_shared_page(): void
    {
        $html = $this->get('/wishlist/shared?i='.ShareCodec::encode([self::A]).'&t='.urlencode('x" onmouseover="alert(1)'))->getContent();

        $this->assertStringNotContainsString('x" onmouseover', $html);
        $this->assertStringContainsString('x&quot; onmouseover=&quot;alert(1)', $html);
    }

    #[Test]
    public function list_titles_and_notes_are_escaped_for_everyone_who_sees_them(): void
    {
        $list = Wishlist::make(Owner::user('jo'), 'Mine" onclick="x')->setDefault()->add(Item::make(self::A, ['notes' => 'Size" onclick="x']));
        $this->wishlists()->save($list);

        $shared = $this->get('/wishlist/shared/'.$list->shareKey())->getContent();
        $this->assertStringNotContainsString('" onclick="x', $shared);

        $this->actingAs($this->user('jo@example.com'));
        $tag = $this->antlers('{{ wishlist:render partial="probe" }}');
        $this->assertStringContainsString('title="Mine&quot; onclick=&quot;x"', $tag);
        $this->assertStringContainsString('data-note="Size&quot; onclick=&quot;x"', $tag);
    }

    #[Test]
    public function messages_after_a_form_post_are_escaped(): void
    {
        session()->flash('wishlist_success', 'Added to <img src=x onerror=alert(1)>.');

        $this->assertStringNotContainsString('<img', $this->antlers('{{ wishlist:success }}'));
    }

    public static function oddRequests(): array
    {
        return [
            'list as an array' => ['items/add', ['id' => self::A, 'list' => ['x']], 200],
            'id as an array' => ['items/add', ['id' => ['x']], 422],
            'a very long id' => ['items/add', ['id' => str_repeat('a', 5000)], 422],
            'data as text' => ['items/add', ['id' => self::A, 'data' => 'text'], 200],
            'a note as an array' => ['items/add', ['id' => self::A, 'data' => ['notes' => ['x']]], 200],
            'title as an array' => ['lists/create', ['title' => ['x']], 200],
            'rename with an array, and no list yet' => ['lists/rename', ['title' => ['x']], 404],
            'key as an array' => ['lists/import', ['key' => ['x']], 200],
            'ids as text' => ['lists/import', ['ids' => 'x'], 200],
            'i as an array' => ['lists/import', ['i' => ['x']], 200],
            'default with an array, and no list yet' => ['lists/default', ['list' => ['x']], 404],
            'redirect as an array' => ['items/add', ['id' => self::A, '_redirect' => ['x']], 200],
        ];
    }

    #[Test]
    #[DataProvider('oddRequests')]
    public function odd_requests_get_an_answer_not_an_error(string $action, array $payload, int $status): void
    {
        $this->actingAs($this->user())->postJson("/!/wishlist/{$action}", $payload)->assertStatus($status);
    }

    #[Test]
    public function odd_links_get_an_answer_not_an_error(): void
    {
        $this->getJson('/!/wishlist/resolve?i[]=x')->assertOk()->assertJsonPath('items', []);
        $this->getJson('/!/wishlist/resolve?ids=x')->assertOk();
        $this->get('/!/wishlist/render?view[]=x')->assertStatus(400);
        $this->get('/!/wishlist/render?view=not-signed')->assertStatus(400);
        $this->get('/wishlist/shared?i[]=x&t[]=y')->assertOk()->assertSee('This wishlist is empty');
        $this->get('/wishlist/shared?i=~!!!.short.'.str_repeat('A', 22))->assertOk();
        $this->get('/wishlist/shared/'.str_repeat('a', 300))->assertNotFound();
    }

    #[Test]
    public function a_malformed_list_file_does_not_break_the_others(): void
    {
        $this->wishlists()->save($good = Wishlist::make(Owner::user('shopper'), 'Good')->setDefault()->add(Item::make(self::A)));

        $directory = rtrim(Stache::store('wishlists')->directory(), '/');
        file_put_contents("{$directory}/not-yaml.yaml", "items: [unclosed\n");
        file_put_contents("{$directory}/a-string.yaml", "just some text\n");
        file_put_contents("{$directory}/items-text.yaml", "owner: 'user:shopper'\nitems: notalist\n");
        file_put_contents("{$directory}/bad-items.yaml", "owner: 'user:shopper'\ntitle: [1, 2]\ndefault: maybe\nitems:\n  - nope\n  - { data: x }\n  - { id: [1] }\n  - { id: kept, added_at: 'not a date', data: { notes: [x], quantity: 2 } }\n");
        file_put_contents("{$directory}/bad-owner.yaml", "owner: banana\nitems: []\n");
        Stache::store('wishlists')->clear();

        $repository = app(WishlistRepository::class);
        $this->assertCount(6, $repository->all());

        $bad = $repository->find('bad-items');
        $this->assertSame('My Wishlist', $bad->title());
        $this->assertSame(['kept'], $bad->items()->map->reference->all());
        $this->assertSame(['quantity' => 2], $bad->item('kept')->data);

        $this->actingAs($user = $this->user())->getJson('/!/wishlist/state')->assertOk();
        $this->actingAs($user)->postJson('/!/wishlist/items/add', ['id' => self::A, 'list' => $good->id()])->assertOk();

        $this->actingAs($this->user('admin@example.com', ['view wishlists']))->get(cp_route('wishlist.index'))->assertOk();
        $this->artisan('wishlist:prune', ['--days' => 0])->assertSuccessful();
    }
}
