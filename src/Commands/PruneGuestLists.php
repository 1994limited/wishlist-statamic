<?php

namespace NineteenNinetyFour\Wishlist\Commands;

use Illuminate\Console\Command;
use NineteenNinetyFour\Wishlist\Contracts\WishlistRepository;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Wishlist;
use Statamic\Console\RunsInPlease;

/**
 * Delete guests' lists nobody has changed for a while. Their cookie has
 * most likely gone, so nobody can reach them but by a share link.
 */
class PruneGuestLists extends Command
{
    use RunsInPlease;

    protected $signature = 'wishlist:prune {--days=90 : Lists untouched for this many days}';

    protected $description = 'Delete guests\' wishlists that haven\'t changed in a while';

    public function handle(WishlistRepository $repository): int
    {
        if (! $repository->storesOnServer()) {
            $this->components->info('The browser driver keeps no lists on the server.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays((int) $this->option('days'));

        $stale = $repository->all()->filter(fn (Wishlist $list) => Owner::fromString($list->owner())->isGuest()
            && ($list->updatedAt() === null || $list->updatedAt()->lt($cutoff)));

        $stale->each(fn (Wishlist $list) => $repository->delete($list));

        $this->components->info("Deleted {$stale->count()} guest wishlists untouched since {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
