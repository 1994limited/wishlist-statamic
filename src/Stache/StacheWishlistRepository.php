<?php

namespace NineteenNinetyFour\Wishlist\Stache;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use NineteenNinetyFour\Wishlist\Contracts\WishlistRepository;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Wishlist;
use Statamic\Facades\Stache;

class StacheWishlistRepository implements WishlistRepository
{
    protected function store(): WishlistsStore
    {
        return Stache::store('wishlists');
    }

    public function find(string $id): ?Wishlist
    {
        return $this->store()->getItem($id);
    }

    public function findByShareKey(string $key): ?Wishlist
    {
        $id = $this->store()->index('share_key')->items()->search($key, true);

        return $id === false ? null : $this->find($id);
    }

    public function forOwner(Owner $owner): Collection
    {
        $keys = $this->store()->index('owner')->items()
            ->filter(fn ($value) => $value === (string) $owner)
            ->keys();

        return $this->sortForOwner($this->store()->getItems($keys)->filter()->values());
    }

    public function all(): Collection
    {
        return $this->store()->getItems($this->store()->paths()->keys())->filter()->values();
    }

    public function paginate(int $perPage = 25, int $page = 1, ?string $search = null): LengthAwarePaginator
    {
        $lists = $this->all()
            ->when($search, fn ($lists) => $lists->filter(fn (Wishlist $list) => str_contains(mb_strtolower($list->title().' '.$list->owner()), mb_strtolower($search))))
            ->sortByDesc(fn (Wishlist $list) => $list->updatedAt()?->getTimestamp() ?? 0)
            ->values();

        return new LengthAwarePaginator($lists->forPage($page, $perPage)->values(), $lists->count(), $perPage, $page);
    }

    public function save(Wishlist $wishlist): void
    {
        $this->store()->save($wishlist);
    }

    public function delete(Wishlist $wishlist): void
    {
        $this->store()->delete($wishlist);
    }

    public function popular(int $limit = 10): Collection
    {
        return $this->all()
            ->flatMap(fn (Wishlist $list) => $list->items()->map(fn ($item) => $item->reference))
            ->countBy()
            ->sortDesc()
            ->take($limit);
    }

    public function storesOnServer(): bool
    {
        return true;
    }

    protected function sortForOwner(Collection $lists): Collection
    {
        return $lists->sortBy([
            fn (Wishlist $a, Wishlist $b) => $b->isDefault() <=> $a->isDefault(),
            fn (Wishlist $a, Wishlist $b) => ($a->createdAt()?->getTimestamp() ?? 0) <=> ($b->createdAt()?->getTimestamp() ?? 0),
        ])->values();
    }
}
