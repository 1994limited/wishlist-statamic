<?php

namespace NineteenNinetyFour\Wishlist\Tests\Feature;

use NineteenNinetyFour\Wishlist\Item;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Presenter;
use NineteenNinetyFour\Wishlist\ShareCodec;
use NineteenNinetyFour\Wishlist\Tests\TestCase;
use NineteenNinetyFour\Wishlist\Wishlist;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

class SharedPageTest extends TestCase
{
    protected const A = '11111111-1111-4111-8111-111111111111';

    protected const B = '22222222-2222-4222-8222-222222222222';

    protected function setUp(): void
    {
        parent::setUp();

        $this->product(self::A, 'Linen Shirt');
        $this->product(self::B, 'Wool Coat');
    }

    protected function sharedList(): Wishlist
    {
        $list = Wishlist::make(Owner::user('jo'), 'Jo’s birthday')->setDefault()
            ->add(Item::make(self::A, ['notes' => 'Size M', 'quantity' => 2]))
            ->add(Item::make(self::B));
        $this->wishlists()->save($list);

        return $list;
    }

    #[Test]
    public function a_share_link_shows_the_list_read_only(): void
    {
        $list = $this->sharedList();

        $this->get('/wishlist/shared/'.$list->shareKey())
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertSee('<title>Jo’s birthday</title>', false)
            ->assertSeeInOrder(['Jo’s birthday', '2 items', 'Linen Shirt', '× 2', 'Size M', 'Wool Coat', 'Save these to my wishlist'], false)
            ->assertSee('href="/products/linen-shirt"', false)
            ->assertSee('name="key" value="'.$list->shareKey().'"', false);
    }

    #[Test]
    public function the_owner_is_not_offered_to_save_their_own_list(): void
    {
        $this->user('jo@example.com');
        $list = $this->sharedList();

        $this->actingAs(User::find('jo'))
            ->get('/wishlist/shared/'.$list->shareKey())
            ->assertOk()
            ->assertDontSee('Save these to my wishlist');
    }

    #[Test]
    public function entries_unpublished_or_deleted_since_are_left_out(): void
    {
        $list = $this->sharedList();
        Entry::find(self::A)->published(false)->save();
        Entry::find(self::B)->delete();

        $this->get('/wishlist/shared/'.$list->shareKey())
            ->assertOk()
            ->assertSee('0 items')
            ->assertSee('This wishlist is empty')
            ->assertDontSee('Linen Shirt');
    }

    #[Test]
    public function entries_expired_since_are_left_out(): void
    {
        $this->event('55555555-5555-4555-8555-555555555555', 'Summer Fair', now()->addWeek()->toDateString());
        $this->event('66666666-6666-4666-8666-666666666666', 'Spring Fair', now()->addWeek()->toDateString());
        Collection::find('events')->futureDateBehavior('public')->save();
        $list = Wishlist::make(Owner::user('jo'))->setDefault()
            ->add(Item::make('55555555-5555-4555-8555-555555555555'))
            ->add(Item::make('66666666-6666-4666-8666-666666666666'));
        $this->wishlists()->save($list);

        Entry::find('66666666-6666-4666-8666-666666666666')->date(now()->subDay()->toDateString())->save();

        $this->get('/wishlist/shared/'.$list->shareKey())
            ->assertOk()
            ->assertSee('1 item')
            ->assertSee('Summer Fair')
            ->assertDontSee('Spring Fair');
    }

    #[Test]
    public function an_unknown_key_is_not_found(): void
    {
        $this->get('/wishlist/shared/nope')->assertNotFound();
    }

    #[Test]
    public function a_link_can_carry_the_items_themselves(): void
    {
        $this->get('/wishlist/shared?'.http_build_query(['i' => ShareCodec::encode([self::B, self::A, 'not-a-thing']), 't' => '<b>Wedding</b>']))
            ->assertOk()
            ->assertSee('<title>Wedding</title>', false)
            ->assertSeeInOrder(['2 items', 'Wool Coat', 'Linen Shirt'])
            ->assertSee('name="i" value="'.ShareCodec::encode([self::B, self::A]).'"', false);
    }

    #[Test]
    public function the_template_and_route_can_be_changed(): void
    {
        config(['wishlist.share.template' => 'my-shared']);
        file_put_contents(__DIR__.'/../__fixtures__/views/my-shared.antlers.html', '<p>Mine: {{ title }} ({{ count }})</p>{{ shared_wishlist:items }}<i>{{ title }}</i>{{ /shared_wishlist:items }}');

        try {
            $this->get('/wishlist/shared/'.$this->sharedList()->shareKey())
                ->assertOk()
                ->assertSee('<p>Mine: Jo’s birthday (2)</p><i>Linen Shirt</i><i>Wool Coat</i>', false);
        } finally {
            unlink(__DIR__.'/../__fixtures__/views/my-shared.antlers.html');
        }
    }

    #[Test]
    public function sharing_can_be_switched_off(): void
    {
        $list = $this->sharedList();
        config(['wishlist.share.enabled' => false]);

        $this->get('/wishlist/shared/'.$list->shareKey())->assertNotFound();
        $this->get('/wishlist/shared?i='.ShareCodec::encode([self::A]))->assertNotFound();
        $this->assertNull(app(Presenter::class)->shareUrl($list));
    }
}
