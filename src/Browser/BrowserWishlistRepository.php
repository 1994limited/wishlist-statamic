<?php

namespace NineteenNinetyFour\Wishlist\Browser;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use LogicException;
use NineteenNinetyFour\Wishlist\Contracts\WishlistRepository;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Wishlist;

/**
 * The browser driver: lists live in visitors' localStorage, so the server
 * has none to find and nowhere to save one.
 */
class BrowserWishlistRepository implements WishlistRepository
{
    public function find(string $id): ?Wishlist
    {
        return null;
    }

    public function findByShareKey(string $key): ?Wishlist
    {
        return null;
    }

    public function forOwner(Owner $owner): Collection
    {
        return collect();
    }

    public function all(): Collection
    {
        return collect();
    }

    public function paginate(int $perPage = 25, int $page = 1, ?string $search = null): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, $page);
    }

    public function save(Wishlist $wishlist): void
    {
        throw new LogicException('The browser wishlist driver keeps lists in the visitor\'s browser, not on the server.');
    }

    public function delete(Wishlist $wishlist): void
    {
        throw new LogicException('The browser wishlist driver keeps lists in the visitor\'s browser, not on the server.');
    }

    public function popular(int $limit = 10): Collection
    {
        return collect();
    }

    public function storesOnServer(): bool
    {
        return false;
    }
}
