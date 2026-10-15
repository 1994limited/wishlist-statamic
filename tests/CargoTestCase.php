<?php

namespace NineteenNinetyFour\Wishlist\Tests;

use DuncanMcClean\Cargo\Facades\Cart;
use DuncanMcClean\Cargo\ServiceProvider as CargoServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Statamic\Addons\Manifest;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;

/**
 * Wishlist on a site with Cargo installed, as a shop would have it. Tests using it
 * run each test in its own process (#[RunTestsInSeparateProcesses]): Cargo
 * registers routes, search and more in Statamic's static state, which
 * would otherwise reach tests without it. PHPUnit reads that attribute
 * from the test class, not this one.
 */
abstract class CargoTestCase extends TestCase
{
    /** Where Cargo keeps carts, and blueprints are read from, during a test. */
    protected static string $content = __DIR__.'/__fixtures__/cargo-tmp';

    protected const SHIRT = '33333333-3333-4333-8333-333333333333';

    protected const MUG = '44444444-4444-4444-8444-444444444444';

    protected function getPackageProviders($app)
    {
        return [CargoServiceProvider::class, ...parent::getPackageProviders($app)];
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('statamic.cargo', require __DIR__.'/../vendor/duncanmcclean/statamic-cargo/config/cargo.php');
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // Statamic boots only the addons in its manifest, and the test case
        // lists just this one. Cargo is installed alongside it on a site.
        $app->make(Manifest::class)->manifest['duncanmcclean/statamic-cargo'] = [
            'id' => 'duncanmcclean/statamic-cargo',
            'slug' => 'cargo',
            'version' => '1.15.0',
            'namespace' => 'DuncanMcClean\\Cargo',
            'autoload' => 'src',
            'provider' => CargoServiceProvider::class,
        ];

        foreach (['carts', 'orders', 'discounts'] as $store) {
            $app['config']->set("statamic.cargo.{$store}.directory", static::$content.'/'.$store);
        }
        $app['config']->set('statamic.cargo.taxes.tax_classes.directory', static::$content.'/tax-classes');
        $app['config']->set('statamic.cargo.taxes.tax_zones.directory', static::$content.'/tax-zones');
    }

    protected function setUp(): void
    {
        $files = new Filesystem;
        $files->deleteDirectory(static::$content);
        $files->ensureDirectoryExists(static::$content.'/blueprints/collections/products');
        $files->put(static::$content.'/blueprints/collections/products/product.yaml', <<<'YAML'
title: Product
fields:
  - { handle: title, field: { type: text } }
  - { handle: price, field: { type: money } }
  - { handle: stock, field: { type: integer } }
  - { handle: product_variants, field: { type: product_variants, option_fields: [{ handle: stock, field: { type: integer } }] } }
YAML);

        parent::setUp();

        Site::setSites(['default' => ['name' => 'Shop', 'url' => '/', 'locale' => 'en_GB', 'attributes' => ['currency' => 'GBP']]])->save();
        Blueprint::setDirectory(static::$content.'/blueprints');
    }

    protected function tearDown(): void
    {
        Cart::forgetCurrentCart();

        parent::tearDown();

        (new Filesystem)->deleteDirectory(static::$content);
    }

    /**
     * A shirt in S (£15, 3 left) and M (£17, sold out).
     */
    protected function shirt(): void
    {
        Entry::make()->collection('products')->blueprint('product')->id(self::SHIRT)->slug('shirt')->data([
            'title' => 'Linen Shirt',
            'product_variants' => [
                'variants' => [['name' => 'Size', 'values' => ['S', 'M']]],
                'options' => [
                    ['key' => 'S', 'variant' => 'S', 'price' => 1500, 'stock' => 3],
                    ['key' => 'M', 'variant' => 'M', 'price' => 1700, 'stock' => 0],
                ],
            ],
        ])->save();
    }

    /**
     * A mug, £8, without variants or stock.
     */
    protected function mug(int $price = 800): void
    {
        Entry::make()->collection('products')->blueprint('product')->id(self::MUG)->slug('mug')->data(['title' => 'Mug', 'price' => $price])->save();
    }
}
