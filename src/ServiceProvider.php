<?php

namespace NineteenNinetyFour\Wishlist;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use NineteenNinetyFour\Wishlist\Browser\BrowserWishlistRepository;
use NineteenNinetyFour\Wishlist\Commands\PruneGuestLists;
use NineteenNinetyFour\Wishlist\Commands\TransferWishlists;
use NineteenNinetyFour\Wishlist\Contracts\WishlistRepository;
use NineteenNinetyFour\Wishlist\Database\EloquentWishlistRepository;
use NineteenNinetyFour\Wishlist\Listeners\MergeGuestLists;
use NineteenNinetyFour\Wishlist\Stache\StacheWishlistRepository;
use NineteenNinetyFour\Wishlist\Stache\WishlistsStore;
use NineteenNinetyFour\Wishlist\Tags\WishlistTags;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Facades\Stache;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;

class ServiceProvider extends AddonServiceProvider
{
    protected $tags = [
        WishlistTags::class,
    ];

    protected $commands = [
        TransferWishlists::class,
        PruneGuestLists::class,
    ];

    protected $vite = [
        'input' => [
            'resources/js/cp.js',
            'resources/css/cp.css',
        ],
        'publicDirectory' => 'resources/dist',
        'hotFile' => __DIR__.'/../resources/dist/hot',
    ];

    /**
     * The package is 1994/wishlist-statamic; its config, views and
     * translations are all read as plain "wishlist" instead, so they're
     * loaded by hand. Routes too: actions live at /!/wishlist, not
     * /!/wishlist-statamic.
     */
    protected $config = false;

    protected $translations = false;

    protected $routes = [
        'web' => null,
        'cp' => __DIR__.'/../routes/cp.php',
        'actions' => null,
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/wishlist.php', 'wishlist');

        // The file driver's lists are cached by the Stache, and Laravel 13's
        // cache unserialises only the classes it is told about.
        $this->registerSerializableClasses([Wishlist::class, Item::class, CarbonImmutable::class]);

        $this->app->singleton(WishlistRepository::class, fn ($app) => $app->make(static::repositoryFor(config('wishlist.driver', 'file'))));
        $this->app->singleton(Wishlists::class);
        $this->app->singleton(Presenter::class);
        $this->app->bind(CurrentOwner::class, fn ($app) => new CurrentOwner($app['request']));
    }

    /**
     * The repository class for a driver name.
     */
    public static function repositoryFor(string $driver): string
    {
        return match ($driver) {
            'file' => StacheWishlistRepository::class,
            'database' => EloquentWishlistRepository::class,
            'browser' => BrowserWishlistRepository::class,
            default => throw new InvalidArgumentException("Unknown wishlist driver [{$driver}]. Use file, database or browser."),
        };
    }

    public function bootAddon(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'wishlist');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'wishlist');

        $this->publishes([__DIR__.'/../config/wishlist.php' => config_path('wishlist.php')], 'wishlist-config');
        $this->publishes([__DIR__.'/../resources/views/shared.antlers.html' => resource_path('views/wishlist/shared.antlers.html')], 'wishlist-views');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'wishlist-migrations');

        // The database driver's tables are migrated with the site's own
        // migrations. With other drivers they aren't made at all.
        if (config('wishlist.driver') === 'database') {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->registerStacheStore();
        $this->registerFrontendRoutes();

        Event::listen(Login::class, MergeGuestLists::class);

        Permission::extend(function () {
            Permission::group('wishlist', 'Wishlist', function () {
                Permission::register('view wishlists', function ($permission) {
                    $permission->label('View visitors\' wishlists')->children([
                        Permission::make('delete wishlists')->label('Delete wishlists'),
                    ]);
                });
            });
        });

        Nav::extend(function ($nav) {
            $nav->content('Wishlists')
                ->route('wishlist.index')
                ->icon('favorite-heart')
                ->can('view wishlists');
        });
    }

    /**
     * The file driver's lists are a Stache store of their own, so they are
     * indexed and cached as entries are, and warmed and cleared with
     * `php please stache:*`. Registered for the transfer command too.
     */
    protected function registerStacheStore(): void
    {
        $store = app(WishlistsStore::class)->directory(config('wishlist.drivers.file.directory', storage_path('wishlists')));

        Stache::registerStore($store);
    }

    protected function registerFrontendRoutes(): void
    {
        Statamic::pushActionRoutes(function () {
            Route::prefix('wishlist')->name('wishlist.')->group(__DIR__.'/../routes/actions.php');
        });

        Statamic::pushWebRoutes(function () {
            require __DIR__.'/../routes/web.php';
        });
    }
}
