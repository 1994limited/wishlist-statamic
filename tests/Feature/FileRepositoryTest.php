<?php

namespace NineteenNinetyFour\Wishlist\Tests\Feature;

use NineteenNinetyFour\Wishlist\Item;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Stache\StacheWishlistRepository;
use NineteenNinetyFour\Wishlist\Tests\TestCase;
use NineteenNinetyFour\Wishlist\Wishlist;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Stache;
use Statamic\Facades\YAML;

class FileRepositoryTest extends TestCase
{
    use RepositoryContract;

    #[Test]
    public function the_file_driver_is_a_stache_store(): void
    {
        $this->assertInstanceOf(StacheWishlistRepository::class, $this->repository());
    }

    #[Test]
    public function each_list_is_a_yaml_file_named_by_its_id(): void
    {
        $list = Wishlist::make(Owner::user('jo'), 'Birthday')->add(Item::make('a1', ['quantity' => 2]))->touch();
        $this->repository()->save($list);

        $path = rtrim(Stache::store('wishlists')->directory(), '/').'/'.$list->id().'.yaml';

        $this->assertFileExists($path);
        $data = YAML::parse(file_get_contents($path));
        $this->assertSame('Birthday', $data['title']);
        $this->assertSame('user:jo', $data['owner']);
        $this->assertSame('a1', $data['items'][0]['id']);
        $this->assertSame(['quantity' => 2], $data['items'][0]['data']);
    }

    #[Test]
    public function a_file_added_by_hand_is_picked_up_by_the_stache(): void
    {
        $directory = rtrim(Stache::store('wishlists')->directory(), '/');
        @mkdir($directory, 0777, true);
        file_put_contents($directory.'/hand-made.yaml', YAML::dump([
            'title' => 'From a file',
            'owner' => 'user:jo',
            'share_key' => 'handmadekey',
            'items' => [['id' => 'a1', 'added_at' => '2026-01-01T00:00:00+00:00']],
        ]));

        Stache::store('wishlists')->clear();

        $this->assertSame('From a file', $this->repository()->find('hand-made')?->title());
        $this->assertSame('hand-made', $this->repository()->findByShareKey('handmadekey')?->id());
        $this->assertCount(1, $this->repository()->forOwner(Owner::user('jo')));
    }
}
