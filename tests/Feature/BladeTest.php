<?php

namespace NineteenNinetyFour\Wishlist\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use NineteenNinetyFour\Wishlist\Item;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Tests\TestCase;
use NineteenNinetyFour\Wishlist\Wishlist;
use PHPUnit\Framework\Attributes\Test;

/**
 * The tags from Blade templates, with Statamic's <s:…> tags and its
 * Statamic::tag() helper, as the docs' Blade examples use them.
 */
class BladeTest extends TestCase
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

    protected function renderBlade(string $template, array $data = []): string
    {
        $this->wishlists();

        return trim(preg_replace('/\s+/', ' ', Blade::render($template, $data)));
    }

    #[Test]
    public function the_default_list_and_its_items(): void
    {
        $this->assertSame(
            'My Wishlist (1): Linen Shirt /products/linen-shirt Size M;',
            $this->renderBlade('<s:wishlist>{{ $title }} ({{ $count }}): @foreach ($items as $item){{ $item[\'title\'] }} {{ $item[\'url\'] }} {{ $item[\'wishlist_item\'][\'notes\'] }};@endforeach</s:wishlist>'),
        );
    }

    #[Test]
    public function every_list_and_a_chosen_lists_items(): void
    {
        $this->assertSame('My Wishlist=1,Gifts=2,', $this->renderBlade('<s:wishlist:lists>{{ $title }}={{ $count }},</s:wishlist:lists>'));
        $this->assertSame('Linen Shirt|Wool Coat|', $this->renderBlade('<s:wishlist:items :list="$list">{{ $title }}|</s:wishlist:items>', ['list' => $this->gifts->id()]));
    }

    #[Test]
    public function has_and_count(): void
    {
        $this->assertSame('yes no', $this->renderBlade(
            '@if (Statamic::tag(\'wishlist:has\')->param(\'id\', $a)->fetch()) yes @else no @endif @if (Statamic::tag(\'wishlist:has\')->param(\'id\', $b)->fetch()) yes @else no @endif',
            ['a' => self::A, 'b' => self::B],
        ));

        $this->assertSame('1|3', $this->renderBlade('<s:wishlist:count />|<s:wishlist:count list="all" />'));
    }

    #[Test]
    public function the_save_button_is_a_form_with_its_state(): void
    {
        $html = $this->renderBlade('<s:wishlist:toggle :id="$id"><button type="submit"><span data-wishlist-when="not-added">Save</span><span data-wishlist-when="added">Saved</span></button></s:wishlist:toggle>', ['id' => self::A]);

        $this->assertStringContainsString('action="http://localhost/!/wishlist/items/toggle"', $html);
        $this->assertStringContainsString('data-wishlist-id="'.self::A.'"', $html);
        $this->assertStringContainsString('data-wishlist-active="true"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('<span data-wishlist-when="added">Saved</span>', $html);
    }

    #[Test]
    public function adding_with_a_note_and_the_list_forms(): void
    {
        $add = $this->renderBlade('<s:wishlist:add :id="$id"><textarea name="notes"></textarea><button>Add</button></s:wishlist:add>', ['id' => self::B]);
        $this->assertStringContainsString('/!/wishlist/items/add', $add);
        $this->assertStringContainsString('<textarea name="notes"></textarea>', $add);

        $create = $this->renderBlade('<s:wishlist:create_list><input name="title"><button>Start another list</button></s:wishlist:create_list>');
        $this->assertStringContainsString('/!/wishlist/lists', $create);
    }

    #[Test]
    public function the_share_link_and_the_script(): void
    {
        $this->assertStringContainsString('/wishlist/shared/', $this->renderBlade('<s:wishlist:share_url />'));
        $this->assertStringContainsString('<script', $this->renderBlade('<s:wishlist:scripts />'));
    }

    #[Test]
    public function a_list_can_be_drawn_with_a_blade_partial(): void
    {
        $html = $this->renderBlade('<s:wishlist:render partial="saved-blade">Loading…</s:wishlist:render>');

        $this->assertStringContainsString('<section class="saved-blade"><h2>My Wishlist</h2><p>Linen Shirt</p></section>', $html);
    }

    #[Test]
    public function the_shared_page_sits_in_a_blade_layout(): void
    {
        config(['wishlist.share.layout' => 'blade-layout']);

        $this->get('/wishlist/shared/'.$this->gifts->shareKey())
            ->assertOk()
            ->assertSee('<title>Gifts</title>', false)
            ->assertSee('<body class="blade-layout">', false)
            ->assertSeeInOrder(['Gifts', 'Linen Shirt', 'Wool Coat'], false);
    }

    #[Test]
    public function the_shared_page_can_be_a_blade_template(): void
    {
        config(['wishlist.share.template' => 'shared-blade']);

        $this->get('/wishlist/shared/'.$this->default->shareKey())
            ->assertOk()
            ->assertSee('<title>My Wishlist</title>', false)
            ->assertSee('<body class="blade-site">', false)
            ->assertSee('<a href="/products/linen-shirt">Linen Shirt</a> Size M', false);
    }

    #[Test]
    public function the_attributes_for_your_own_button(): void
    {
        $html = $this->renderBlade('<button {!! Statamic::tag(\'wishlist:toggle_attrs\')->param(\'id\', $id)->fetch() !!}>♥</button>', ['id' => self::A]);

        $this->assertStringContainsString('data-wishlist-toggle="'.self::A.'"', $html);
        $this->assertStringContainsString('data-wishlist-active="true"', $html);
    }

    #[Test]
    public function messages_after_a_form_without_javascript(): void
    {
        session()->flash('wishlist_success', 'Added to My Wishlist.');

        $this->assertSame('<p class="notice">Added to My Wishlist.</p>', $this->renderBlade(
            "@if (\$message = Statamic::tag('wishlist:success')->fetch())<p class=\"notice\">{{ \$message }}</p>@endif\n"
            .'@if ($message = Statamic::tag(\'wishlist:error\')->fetch())<p class="error">{{ $message }}</p>@endif',
        ));
    }

    #[Test]
    public function a_button_for_each_list(): void
    {
        $html = $this->renderBlade('<s:wishlist:lists><s:wishlist:add :id="$page" :list="$id"><button>Add to {{ $title }}</button></s:wishlist:add></s:wishlist:lists>', ['page' => self::B]);

        $this->assertSame(2, substr_count($html, 'data-wishlist-id="'.self::B.'"'));
        $this->assertStringContainsString('value="'.$this->gifts->id().'"', $html);
        $this->assertStringContainsString('Add to Gifts', $html);
    }

    #[Test]
    public function the_share_link_inside_an_attribute(): void
    {
        $html = $this->renderBlade('<input data-wishlist-share readonly value="<s:wishlist:share_url />">');

        $this->assertMatchesRegularExpression('#^<input data-wishlist-share readonly value="http://localhost/wishlist/shared/[A-Za-z0-9]+">$#', $html);
    }

    #[Test]
    public function lists_as_data_for_blades_own_loops(): void
    {
        $html = $this->renderBlade("@forelse (Statamic::tag('wishlist:lists')->fetch() as \$list)<a href=\"#{{ \$list['id'] }}\">{{ \$list['title'] }} ({{ \$list['count'] }})</a>\n@empty No lists yet.\n@endforelse");

        $this->assertStringContainsString('My Wishlist (1)</a>', $html);
        $this->assertStringContainsString('Gifts (2)</a>', $html);
    }

    #[Test]
    public function the_pages_entry_inside_the_lists_loop(): void
    {
        $html = $this->renderBlade("@php(\$entryId = \$id)\n<s:wishlist:lists><s:wishlist:add :id=\"\$entryId\" :list=\"\$id\"><button>Add to {{ \$title }}</button></s:wishlist:add></s:wishlist:lists>", ['id' => self::B]);

        $this->assertSame(2, substr_count($html, 'data-wishlist-id="'.self::B.'"'));
    }

    #[Test]
    public function visitors_text_is_escaped_once_in_the_documented_patterns(): void
    {
        $title = 'Books & Gifts <3 "x"';
        $note = 'For Priya <3 & "co"';
        $this->gifts->setTitle($title)->add(Item::make(self::B, ['notes' => $note]));
        $this->wishlists()->save($this->gifts);

        $list = '<s:wishlist :list="$list"><h2>{!! $title !!}</h2>@foreach ($items as $item)<a href="{{ $item[\'url\'] }}">{{ $item[\'title\'] }}</a><small>{!! $item[\'wishlist_item\'][\'notes\'] ?? \'\' !!}</small>@endforeach</s:wishlist>';
        $html = $this->renderBlade($list, ['list' => $this->gifts->id()]);

        $this->assertStringContainsString('<h2>Books &amp; Gifts &lt;3 &quot;x&quot;</h2>', $html);
        $this->assertStringContainsString('<small>For Priya &lt;3 &amp; &quot;co&quot;</small>', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('<3', $html);

        // Lists, and a message naming one.
        $this->assertStringContainsString('Books &amp; Gifts &lt;3 &quot;x&quot; (2)', $this->renderBlade("@foreach (Statamic::tag('wishlist:lists')->fetch() as \$l){!! \$l['title'] !!} ({{ \$l['count'] }})\n@endforeach"));

        session()->flash('wishlist_success', 'Added to '.$title.'.');
        $this->assertSame('<p>Added to Books &amp; Gifts &lt;3 &quot;x&quot;.</p>', $this->renderBlade("@if (\$message = Statamic::tag('wishlist:success')->fetch())<p>{!! \$message !!}</p>@endif"));
    }

    #[Test]
    public function the_toggle_attributes_cant_be_broken_out_of(): void
    {
        $html = $this->renderBlade('<button {!! Statamic::tag(\'wishlist:toggle_attrs\')->param(\'id\', $id)->fetch() !!}>♥</button>', ['id' => 'x" onmouseover="alert(1)']);

        $this->assertStringNotContainsString('" onmouseover="', $html);
        $this->assertStringContainsString('&quot; onmouseover=&quot;alert(1)', $html);
    }

    #[Test]
    public function a_blade_shared_page_escapes_the_title_once(): void
    {
        config(['wishlist.share.template' => 'shared-blade']);
        $this->default->setTitle('Jo & Sam <3');
        $this->wishlists()->save($this->default);

        $this->get('/wishlist/shared/'.$this->default->shareKey())
            ->assertOk()
            ->assertSee('<h1>Jo &amp; Sam &lt;3</h1>', false)
            ->assertDontSee('&amp;amp;', false);
    }
}
