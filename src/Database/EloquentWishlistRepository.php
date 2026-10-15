<?php

namespace NineteenNinetyFour\Wishlist\Database;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use NineteenNinetyFour\Wishlist\Contracts\WishlistRepository;
use NineteenNinetyFour\Wishlist\Item;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Wishlist;

class EloquentWishlistRepository implements WishlistRepository
{
    public function find(string $id): ?Wishlist
    {
        $model = WishlistModel::with('items')->find($id);

        return $model ? $this->fromModel($model) : null;
    }

    public function findByShareKey(string $key): ?Wishlist
    {
        $model = WishlistModel::with('items')->where('share_key', $key)->first();

        return $model ? $this->fromModel($model) : null;
    }

    public function forOwner(Owner $owner): Collection
    {
        return WishlistModel::with('items')
            ->where('owner', (string) $owner)
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get()
            ->map(fn (WishlistModel $model) => $this->fromModel($model))
            ->values();
    }

    public function all(): Collection
    {
        return WishlistModel::with('items')->get()->map(fn (WishlistModel $model) => $this->fromModel($model))->values();
    }

    public function paginate(int $perPage = 25, int $page = 1, ?string $search = null): LengthAwarePaginator
    {
        $paginator = WishlistModel::with('items')
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->where('title', 'like', '%'.$search.'%')
                ->orWhere('owner', 'like', '%'.$search.'%')))
            ->orderByDesc('updated_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return $paginator->setCollection($paginator->getCollection()->map(fn (WishlistModel $model) => $this->fromModel($model)));
    }

    public function save(Wishlist $wishlist): void
    {
        DB::connection((new WishlistModel)->getConnectionName())->transaction(function () use ($wishlist) {
            WishlistModel::query()->updateOrCreate(['id' => $wishlist->id()], [
                'owner' => $wishlist->owner(),
                'title' => $wishlist->title(),
                'share_key' => $wishlist->shareKey(),
                'is_default' => $wishlist->isDefault(),
                'created_at' => $wishlist->createdAt(),
                'updated_at' => $wishlist->updatedAt(),
            ]);

            $references = $wishlist->items()->map(fn (Item $item) => $item->reference)->all();

            WishlistItemModel::query()
                ->where('wishlist_id', $wishlist->id())
                ->whereNotIn('reference', $references)
                ->delete();

            foreach ($wishlist->items() as $item) {
                WishlistItemModel::query()->updateOrCreate(
                    ['wishlist_id' => $wishlist->id(), 'reference' => $item->reference],
                    ['data' => $item->data ?: null, 'added_at' => $item->addedAt],
                );
            }
        });
    }

    public function delete(Wishlist $wishlist): void
    {
        WishlistItemModel::query()->where('wishlist_id', $wishlist->id())->delete();
        WishlistModel::query()->whereKey($wishlist->id())->delete();
    }

    public function popular(int $limit = 10): Collection
    {
        return WishlistItemModel::query()
            ->select('reference', DB::raw('count(*) as aggregate'))
            ->groupBy('reference')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->pluck('aggregate', 'reference')
            ->map(fn ($count) => (int) $count);
    }

    public function storesOnServer(): bool
    {
        return true;
    }

    protected function fromModel(WishlistModel $model): Wishlist
    {
        return Wishlist::fromArray($model->id, [
            'owner' => $model->owner,
            'title' => $model->title,
            'share_key' => $model->share_key,
            'default' => $model->is_default,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
            'items' => $model->items->map(fn (WishlistItemModel $item) => new Item(
                $item->reference,
                $item->added_at,
                $item->data ?? [],
            ))->all(),
        ]);
    }
}
