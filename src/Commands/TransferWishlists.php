<?php

namespace NineteenNinetyFour\Wishlist\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use NineteenNinetyFour\Wishlist\ServiceProvider;
use Statamic\Console\RunsInPlease;

/**
 * Copy every list from one driver to the other, as when moving a site from
 * flat files to the database (or back). Lists keep their IDs and share
 * keys, so links already sent keep working.
 */
class TransferWishlists extends Command
{
    use RunsInPlease;

    protected $signature = 'wishlist:transfer
        {--from= : file or database}
        {--to= : file or database}
        {--force : Don\'t ask before copying}';

    protected $description = 'Copy wishlists from one storage driver to another';

    public function handle(): int
    {
        $from = $this->option('from');
        $to = $this->option('to');

        foreach ([$from, $to] as $driver) {
            if (! in_array($driver, ['file', 'database'], true)) {
                $this->components->error('Pass --from and --to as "file" or "database". Browser lists are only in visitors\' browsers.');

                return self::FAILURE;
            }
        }

        if ($from === $to) {
            $this->components->error('--from and --to are the same driver.');

            return self::FAILURE;
        }

        if (! $this->tablesExist()) {
            $this->components->error('The wishlist tables don\'t exist yet. Publish the migration and run it first:');
            $this->line('  php please vendor:publish --tag=wishlist-migrations');
            $this->line('  php artisan migrate');

            return self::FAILURE;
        }

        $source = app(ServiceProvider::repositoryFor($from));
        $target = app(ServiceProvider::repositoryFor($to));
        $lists = $source->all();

        if ($lists->isEmpty()) {
            $this->components->info("There are no wishlists in the {$from} driver.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Copy {$lists->count()} wishlists from {$from} to {$to}? Lists with the same ID there are replaced.", true)) {
            return self::FAILURE;
        }

        $this->withProgressBar($lists, fn ($list) => $target->save($list));
        $this->newLine(2);

        $this->components->info("Copied {$lists->count()} wishlists to the {$to} driver. Set WISHLIST_DRIVER={$to} to use them.");

        return self::SUCCESS;
    }

    /**
     * The database driver's tables are only migrated with the site's own
     * migrations once the driver is database, or the migration has been
     * published. Before that, there's nowhere to copy to or from.
     */
    private function tablesExist(): bool
    {
        $schema = Schema::connection(config('wishlist.drivers.database.connection') ?: null);

        return $schema->hasTable(config('wishlist.drivers.database.table', 'wishlists'))
            && $schema->hasTable(config('wishlist.drivers.database.items_table', 'wishlist_items'));
    }
}
