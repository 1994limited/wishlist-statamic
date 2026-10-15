<?php

namespace NineteenNinetyFour\Wishlist\Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Crypt;
use NineteenNinetyFour\Wishlist\ShareCodec;
use NineteenNinetyFour\Wishlist\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Stache;

/**
 * With the browser driver nothing is kept on the server: the actions that
 * change lists refuse, and the server only renders what links and the
 * script send it.
 */
class BrowserDriverTest extends TestCase
{
    protected string $driver = 'browser';

    protected const A = '11111111-1111-4111-8111-111111111111';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->product(self::A, 'Linen Shirt');
    }

    #[Test]
    public function the_server_keeps_nothing(): void
    {
        $response = $this->postJson('/!/wishlist/items/add', ['id' => self::A])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Wishlists are kept in your browser on this site.');

        $this->assertNull($response->getCookie('wishlist_guest', false));
        $this->assertFalse(is_dir(Stache::store('wishlists')->directory()));

        $this->getJson('/!/wishlist/state')->assertJsonPath('driver', 'browser')->assertJsonPath('lists', []);
    }

    #[Test]
    public function share_links_carry_the_items(): void
    {
        $this->get('/wishlist/shared?i='.ShareCodec::encode([self::A]).'&t=Mine')
            ->assertOk()
            ->assertSee('Linen Shirt');

        $this->get('/wishlist/shared/somekey')->assertNotFound();
    }

    #[Test]
    public function the_script_draws_lists_with_a_signed_partial(): void
    {
        file_put_contents(__DIR__.'/../__fixtures__/views/partials/saved.antlers.html', '{{ count }}:{{ items }}[{{ title }}]{{ /items }}');

        try {
            $html = $this->antlers('{{ wishlist:render partial="saved" }}Loading…{{ /wishlist:render }}');

            $this->assertStringContainsString('Loading…', $html);
            preg_match('/data-wishlist-render="([^"]+)"/', $html, $match);
            $this->assertSame('partials/saved', Crypt::decryptString(html_entity_decode($match[1])));

            $this->get('/!/wishlist/render?view='.urlencode(html_entity_decode($match[1])).'&i='.ShareCodec::encode([self::A, 'nope']))
                ->assertOk()
                ->assertSee('1:[Linen Shirt]', false);

            $this->get('/!/wishlist/render?view=partials/saved&i=')->assertStatus(400);
        } finally {
            unlink(__DIR__.'/../__fixtures__/views/partials/saved.antlers.html');
        }
    }

    #[Test]
    public function a_blade_partial_draws_the_list_too(): void
    {
        file_put_contents(__DIR__.'/../__fixtures__/views/partials/saved.blade.php', '{{ $title }}|@foreach ($items as $item)[{{ $item[\'title\'] }}:{{ $item[\'wishlist_item\'][\'notes\'] ?? \'\' }}]@endforeach');

        try {
            $html = $this->postJson('/!/wishlist/render', [
                'view' => Crypt::encryptString('partials/saved'),
                't' => 'Birthday',
                'items' => [['id' => self::A, 'data' => ['notes' => '<b>Size M</b>']]],
            ])->assertOk()->getContent();

            $this->assertSame('Birthday|[Linen Shirt:Size M]', $html);
        } finally {
            unlink(__DIR__.'/../__fixtures__/views/partials/saved.blade.php');
        }
    }

    #[Test]
    public function posted_items_are_drawn_with_their_notes_held_to_the_saving_rules(): void
    {
        file_put_contents(__DIR__.'/../__fixtures__/views/partials/saved.antlers.html', '{{ title }}|{{ items }}[{{ title }}:{{ wishlist_item:notes }}:{{ wishlist_item:quantity }}:{{ wishlist_item:price }}]{{ /items }}');

        try {
            $html = $this->postJson('/!/wishlist/render', [
                'view' => Crypt::encryptString('partials/saved'),
                't' => '<b>Birthday</b>',
                'items' => [
                    ['id' => self::A, 'data' => ['notes' => 'Size M" onclick="x', 'quantity' => '3', 'price' => '0.01']],
                    ['id' => self::A, 'data' => ['notes' => 'a repeat']],
                    ['id' => ['x']],
                    'nonsense',
                    ['id' => 'not-an-entry'],
                ],
            ])->assertOk()->getContent();

            $this->assertSame('Birthday|[Linen Shirt:Size M&quot; onclick=&quot;x:3:]', $html);

            // Without a signed view, nothing is drawn.
            $this->postJson('/!/wishlist/render', ['view' => 'partials/saved', 'items' => []])->assertStatus(400);
        } finally {
            unlink(__DIR__.'/../__fixtures__/views/partials/saved.antlers.html');
        }
    }

    #[Test]
    public function tags_read_as_empty_and_leave_the_rest_to_the_script(): void
    {
        $this->assertSame('0|false|browser', $this->antlers('{{ wishlist:count }}|{{ if {wishlist:has id="'.self::A.'"} }}true{{ else }}false{{ /if }}|{{ wishlist:driver }}'));

        // The server can't know whether it's saved, so it doesn't say: the labels wait for the script.
        $button = $this->antlers('<button {{ wishlist:toggle_attrs id="'.self::A.'" }}>');
        $this->assertStringContainsString('data-wishlist-toggle="'.self::A.'"', $button);
        $this->assertStringNotContainsString('data-wishlist-active', $button);
        $this->assertStringNotContainsString('aria-pressed', $button);
        $this->assertStringNotContainsString('data-wishlist-active', $this->antlers('{{ wishlist:toggle id="'.self::A.'" }}{{ /wishlist:toggle }}'));
        $this->assertStringContainsString('"driver":"browser"', $this->antlers('{{ wishlist:scripts }}'));

        // A new version of the script is a new URL, so browsers don't keep the old one.
        $this->assertStringContainsString('/!/wishlist/wishlist.js?v='.substr(md5_file(__DIR__.'/../../resources/js/wishlist.js'), 0, 12).'"', $this->antlers('{{ wishlist:scripts }}'));
    }
}
