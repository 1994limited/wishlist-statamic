<?php

namespace NineteenNinetyFour\Wishlist\Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use NineteenNinetyFour\Wishlist\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Changes are rate limited per visitor, so a script can't fill the disk
 * or database with lists. Reading isn't.
 */
class ThrottleTest extends TestCase
{
    protected const A = '11111111-1111-4111-8111-111111111111';

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('wishlist.throttle', '3,1');
    }

    #[Test]
    public function changes_are_rate_limited(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->product(self::A, 'Linen Shirt');
        $user = $this->user();

        foreach (range(1, 3) as $i) {
            $this->actingAs($user)->postJson('/!/wishlist/items/toggle', ['id' => self::A])->assertOk();
        }

        $this->actingAs($user)->postJson('/!/wishlist/items/toggle', ['id' => self::A])->assertStatus(429);
        $this->actingAs($user)->getJson('/!/wishlist/state')->assertOk();
    }
}
