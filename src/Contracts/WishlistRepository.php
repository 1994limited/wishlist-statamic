<?php

namespace NineteenNinetyFour\Wishlist\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Wishlist;

/**
 * Where lists are kept. The file and database drivers implement it; the
 * browser driver keeps nothing on the server.
 */
interface WishlistRepository
{
    public function find(string $id): ?Wishlist;

    public function findByShareKey(string $key): ?Wishlist;

    /**
     * An owner's lists, the default first, then oldest first.
     *
     * @return Collection<int, Wishlist>
     */
    public function forOwner(Owner $owner): Collection;

    /**
     * @return Collection<int, Wishlist>
     */
    public function all(): Collection;

    /**
     * For the Control Panel: newest change first, optionally matching a
     * search on the title or owner.
     */
    public function paginate(int $perPage = 25, int $page = 1, ?string $search = null): LengthAwarePaginator;

    /**
     * Store the list as it is, dates included.
     */
    public function save(Wishlist $wishlist): void;

    public function delete(Wishlist $wishlist): void;

    /**
     * How many lists each reference is on, most first.
     *
     * @return Collection<string, int>
     */
    public function popular(int $limit = 10): Collection;

    /**
     * False for the browser driver, which keeps nothing here.
     */
    public function storesOnServer(): bool;
}
