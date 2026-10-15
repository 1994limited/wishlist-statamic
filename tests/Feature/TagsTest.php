<?php

namespace NineteenNinetyFour\Wishlist\Tests\Feature;

use Illuminate\Support\Facades\Crypt;
use NineteenNinetyFour\Wishlist\Item;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Tests\TestCase;
use NineteenNinetyFour\Wishlist\Wishlist;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Entry;

class TagsTest extends TestCase
{
    protected const A = '11111111-1111-4111-8111-111111111111';

    protected const B = '22222222-2222-4222-8222-222222222222';

    protected Wishlist $default;

    protected Wishlist $gifts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product(self::A, 'Linen Shirt');
        $this->product(self::B, 'Wool Coat');

        $user = $this->user();
        $this->actingAs($user);

        $this->default = Wishlist::make(Owner::user($user))->setDefault()->add(Item::make(self::A, ['notes' => 'Size M']));
        $this->gifts = Wishlist::make(Owner::user($user), 'Gifts')->add(Item::make(self::A))->add(Item::make(self::B));
        $this->wishlists()->save($this->default);
        $this->wishlists()->save($this->gifts);
    }

    #[Test]
    public function the_default_list_and_its_items(): void
    {
        $this->assertSame(
            'My Wishlist (1): Linen Shirt /products/linen-shirt Size M;',
            $this->antlers('{{ wishlist }}{{ title }} ({{ count }}): {{ items }}{{ title }} {{ url }} {{ wishlist_item:notes }};{{ /items }}{{ /wishlist }}'),
        );
    }

    #[Test]
    public function every_list(): void
    {
        $this->assertSame('My Wishlist=1,Gifts=2,', $this->antlers('{{ wishlist:lists }}{{ title }}={{ count }},{{ /wishlist:lists }}'));
    }

    #[Test]
    public function items_of_a_chosen_list(): void
    {
        $this->assertSame('Linen Shirt|Wool Coat|', $this->antlers('{{ wishlist:items list="'.$this->gifts->id().'" }}{{ title }}|{{ /wishlist:items }}'));
        $this->assertSame('Linen Shirt|', $this->antlers('{{ wishlist:items list="'.$this->gifts->id().'" limit="1" }}{{ title }}|{{ /wishlist:items }}'));
    }

    #[Test]
    public function has_and_count(): void
    {
        $has = fn (string $params) => $this->antlers('{{ if {wishlist:has '.$params.'} }}yes{{ else }}no{{ /if }}');

        $this->assertSame('yes', $has('id="'.self::A.'"'));
        $this->assertSame('no', $has('id="'.self::B.'"'));
        $this->assertSame('yes', $has('id="'.self::B.'" list="any"'));
        $this->assertSame('yes', $has('id="'.self::B.'" list="'.$this->gifts->id().'"'));

        $this->assertSame('1|2|3', $this->antlers('{{ wishlist:count }}|{{ wishlist:count list="'.$this->gifts->id().'" }}|{{ wishlist:count list="all" }}'));
    }

    #[Test]
    public function counts_leave_out_entries_deleted_or_unpublished_since(): void
    {
        Entry::find(self::B)->published(false)->save();
        $this->default->add(Item::make('deleted-entry-id'));
        $this->wishlists()->save($this->default);

        $this->assertSame('1|1|2', $this->antlers('{{ wishlist:count }}|{{ wishlist:count list="'.$this->gifts->id().'" }}|{{ wishlist:count list="all" }}'));

        $state = $this->getJson('/!/wishlist/state')->json('lists');
        $this->assertSame([[self::A], [self::A]], array_map(fn ($list) => array_column($list['items'], 'id'), $state));
    }

    #[Test]
    public function the_share_url(): void
    {
        $this->assertSame('http://localhost/wishlist/shared/'.$this->default->shareKey(), $this->antlers('{{ wishlist:share_url }}'));
    }

    #[Test]
    public function a_toggle_form_knows_whether_the_item_is_on_the_list(): void
    {
        $html = $this->antlers('{{ wishlist:toggle id="'.self::A.'" class="save" redirect="/saved" }}<button>{{ if added }}Saved{{ else }}Save{{ /if }}</button>{{ /wishlist:toggle }}');

        $this->assertStringContainsString('action="http://localhost/!/wishlist/items/toggle"', $html);
        $this->assertStringContainsString('class="save"', $html);
        $this->assertStringContainsString('data-wishlist-form="toggle"', $html);
        $this->assertStringContainsString('data-wishlist-active="true"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('<input type="hidden" name="id" value="'.self::A.'">', $html);
        $this->assertStringContainsString('<input type="hidden" name="_redirect" value="/saved">', $html);
        $this->assertStringContainsString('<button>Saved</button>', $html);

        $this->assertStringContainsString('<button>Save</button>', $this->antlers('{{ wishlist:toggle id="'.self::B.'" }}<button>{{ if added }}Saved{{ else }}Save{{ /if }}</button>{{ /wishlist:toggle }}'));
    }

    #[Test]
    public function list_forms_take_the_list_from_the_loop(): void
    {
        $html = $this->antlers('{{ wishlist:lists }}{{ wishlist:delete_list }}<button>Delete {{ title }}</button>{{ /wishlist:delete_list }}{{ /wishlist:lists }}');

        $this->assertStringContainsString('<input type="hidden" name="list" value="'.$this->default->id().'"><button>Delete My Wishlist</button>', $html);
        $this->assertStringContainsString('<input type="hidden" name="list" value="'.$this->gifts->id().'"><button>Delete Gifts</button>', $html);
    }

    #[Test]
    public function form_options_are_not_rendered_as_attributes(): void
    {
        $html = $this->antlers('{{ wishlist:import key="abc" list="'.$this->gifts->id().'" csrf="false" class="copy" }}{{ /wishlist:import }}');

        $this->assertStringContainsString('class="copy"', $html);
        $this->assertStringNotContainsString('csrf=', $html);
        $this->assertStringNotContainsString('name="_token"', $html);
        $this->assertStringContainsString('<input type="hidden" name="list" value="'.$this->gifts->id().'">', $html);
    }

    #[Test]
    public function buttons_say_whether_the_item_is_saved_so_the_right_label_shows_from_the_first_paint(): void
    {
        $saved = $this->antlers('<button {{ wishlist:toggle_attrs id="'.self::A.'" }}>');
        $notSaved = $this->antlers('<button {{ wishlist:toggle_attrs id="'.self::B.'" }}>');

        $this->assertStringContainsString('data-wishlist-active="true" aria-pressed="true"', $saved);
        $this->assertStringContainsString('data-wishlist-active="false" aria-pressed="false"', $notSaved);

        $scripts = $this->antlers('{{ wishlist:scripts }}');
        $this->assertStringStartsWith('<style>', $scripts);
        $this->assertStringContainsString('[data-wishlist-active="true"] [data-wishlist-when="not-added"]', $scripts);
        $this->assertStringContainsString('[data-wishlist-active="false"] [data-wishlist-when="added"]', $scripts);
        $this->assertStringContainsString('select[data-wishlist-lists]:empty{visibility:hidden}', $scripts);
        $this->assertStringContainsString(' defer></script>', $scripts);
    }

    #[Test]
    public function list_forms_outside_a_loop_do_not_take_the_pages_id(): void
    {
        $page = ['id' => 'the-page-entry-id'];

        $create = $this->antlers('{{ wishlist:create_list }}<input name="title">{{ /wishlist:create_list }}', $page);
        $delete = $this->antlers('{{ wishlist:delete_list }}<button>Delete</button>{{ /wishlist:delete_list }}', $page);

        $this->assertStringNotContainsString('name="list"', $create);
        $this->assertStringNotContainsString('the-page-entry-id', $delete);
        $this->assertStringContainsString('<input type="hidden" name="list" value="'.$this->gifts->id().'">', $this->antlers('{{ wishlist:delete_list list="'.$this->gifts->id().'" }}{{ /wishlist:delete_list }}', $page));
    }

    #[Test]
    public function the_script_redraws_the_visitors_own_list_with_its_notes(): void
    {
        file_put_contents(__DIR__.'/../__fixtures__/views/partials/saved.antlers.html', '{{ title }}: {{ items }}[{{ title }}{{ if wishlist_item:notes }} ({{ wishlist_item:notes }}){{ /if }}]{{ /items }}');

        try {
            $view = Crypt::encryptString('partials/saved');

            $this->get('/!/wishlist/render?view='.urlencode($view).'&list='.$this->default->id())
                ->assertOk()
                ->assertSee('My Wishlist: [Linen Shirt (Size M)]', false);

            // Someone else's list isn't theirs to draw: they get their own default.
            $this->actingAs($this->user('other@example.com'));
            $this->get('/!/wishlist/render?view='.urlencode($view).'&list='.$this->gifts->id())
                ->assertOk()
                ->assertDontSee('Gifts');
        } finally {
            unlink(__DIR__.'/../__fixtures__/views/partials/saved.antlers.html');
        }
    }

    #[Test]
    public function render_draws_the_list_with_a_partial_on_the_server(): void
    {
        file_put_contents(__DIR__.'/../__fixtures__/views/partials/saved.antlers.html', '{{ title }}: {{ items }}[{{ title }}]{{ /items }}');

        try {
            $html = $this->antlers('{{ wishlist:render partial="saved" list="'.$this->gifts->id().'" }}');

            $this->assertStringStartsWith('<div data-wishlist-render="', $html);
            $this->assertStringContainsString('data-wishlist-list="'.$this->gifts->id().'"', $html);
            $this->assertStringContainsString('>Gifts: [Linen Shirt][Wool Coat]</div>', $html);
        } finally {
            unlink(__DIR__.'/../__fixtures__/views/partials/saved.antlers.html');
        }
    }

    #[Test]
    public function a_visitor_with_no_lists_sees_an_empty_default(): void
    {
        $this->actingAs($this->user('new@example.com'));

        $this->assertSame('My Wishlist|0|empty|', $this->antlers('{{ wishlist }}{{ title }}|{{ count }}|{{ if no_results }}empty{{ /if }}|{{ share_url }}{{ /wishlist }}'));
        $this->assertSame('none', $this->antlers('{{ wishlist:lists }}{{ if no_results }}none{{ else }}{{ title }}{{ /if }}{{ /wishlist:lists }}'));
        $this->assertSame('none', $this->antlers('{{ wishlist:items }}{{ if no_results }}none{{ else }}{{ title }}{{ /if }}{{ /wishlist:items }}'));
    }

    #[Test]
    public function messages_after_a_form_post(): void
    {
        session()->flash('wishlist_success', 'Added to My Wishlist.');

        $this->assertSame('Added to My Wishlist.', $this->antlers('{{ wishlist:success }}'));
    }
}
