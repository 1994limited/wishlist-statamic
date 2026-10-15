<?php

namespace NineteenNinetyFour\Wishlist\Tests;

use NineteenNinetyFour\Wishlist\ServiceProvider;
use NineteenNinetyFour\Wishlist\Wishlists;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\FakesRoles;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{
    use FakesRoles, PreventsSavingStacheItemsToDisk;

    protected string $addonServiceProvider = ServiceProvider::class;

    /** The storage driver the test runs with. */
    protected string $driver = 'file';

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('wishlist.driver', $this->driver);
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('view.paths', [__DIR__.'/__fixtures__/views']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->driver === 'database') {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        Collection::make('products')->routes('/products/{slug}')->save();
        Collection::make('pages')->routes('/{slug}')->save();
    }

    protected function product(string $id, string $title, bool $published = true): \Statamic\Contracts\Entries\Entry
    {
        $entry = Entry::make()->collection('products')->id($id)->slug(str($title)->slug())->published($published)->data(['title' => $title]);
        $entry->save();

        return $entry;
    }

    /**
     * An entry in a dated collection that hides future and past entries,
     * as for events: Statamic reports it scheduled or expired.
     */
    protected function event(string $id, string $title, string $date): \Statamic\Contracts\Entries\Entry
    {
        Collection::make('events')->dated(true)->futureDateBehavior('private')->pastDateBehavior('private')->routes('/events/{slug}')->save();

        $entry = Entry::make()->collection('events')->id($id)->slug(str($title)->slug())->date($date)->data(['title' => $title]);
        $entry->save();

        return $entry;
    }

    protected function page(string $id, string $title): \Statamic\Contracts\Entries\Entry
    {
        $entry = Entry::make()->collection('pages')->id($id)->slug(str($title)->slug())->data(['title' => $title]);
        $entry->save();

        return $entry;
    }

    protected function tag(string $slug): \Statamic\Contracts\Taxonomies\Term
    {
        Taxonomy::make('tags')->save();
        $term = Term::make()->taxonomy('tags')->slug($slug)->data(['title' => ucfirst($slug)]);
        $term->save();

        return $term;
    }

    protected function user(string $email = 'shopper@example.com', array $permissions = []): \Statamic\Contracts\Auth\User
    {
        if ($permissions) {
            $this->setTestRoles(['tester' => ['access cp', ...$permissions]]);
        }

        $user = User::make()->email($email)->id((string) str($email)->before('@')->slug());

        if ($permissions) {
            $user->assignRole('tester');
        }

        $user->save();

        return $user;
    }

    /**
     * Render Antlers as a site template would (trusted, so tags run).
     */
    protected function antlers(string $template, array $data = []): string
    {
        return (string) Antlers::parse($template, $data, true);
    }

    protected function wishlists(): Wishlists
    {
        $wishlists = app(Wishlists::class);
        $wishlists->forget();

        return $wishlists;
    }
}
