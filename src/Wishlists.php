<?php

namespace NineteenNinetyFour\Wishlist;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use NineteenNinetyFour\Wishlist\Contracts\WishlistRepository;
use NineteenNinetyFour\Wishlist\Events\GuestListsMerged;
use NineteenNinetyFour\Wishlist\Events\ItemAdded;
use NineteenNinetyFour\Wishlist\Events\ItemRemoved;
use NineteenNinetyFour\Wishlist\Events\WishlistDeleted;
use NineteenNinetyFour\Wishlist\Events\WishlistSaved;
use NineteenNinetyFour\Wishlist\Integrations\Cargo;
use Statamic\Contracts\Auth\Protect\Protectable;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Data;

/**
 * What an owner can do with their lists, for the file and database drivers.
 * Every change goes through here, so the rules (what may be added, how
 * many, which values are kept) hold whichever way it was asked for.
 *
 * A list ID of null means the owner's default list, made when first needed.
 */
class Wishlists
{
    /**
     * The cookie the script keeps the picked list in, so the server can
     * draw it. A list ID, not a secret: it's only used if it's one of the
     * visitor's own lists.
     */
    public const PICKED_COOKIE = 'wishlist_picked';

    /** @var array<string, Collection<int, Wishlist>> */
    protected array $memo = [];

    public function __construct(protected WishlistRepository $repository) {}

    public function repository(): WishlistRepository
    {
        return $this->repository;
    }

    public function storesOnServer(): bool
    {
        return $this->repository->storesOnServer();
    }

    /**
     * @return Collection<int, Wishlist>
     */
    public function lists(Owner $owner): Collection
    {
        return $this->memo[(string) $owner] ??= $this->repository->forOwner($owner);
    }

    /**
     * One of the owner's lists, or their default with no ID. Null when
     * there's no such list, or it's someone else's.
     */
    public function find(Owner $owner, ?string $listId = null): ?Wishlist
    {
        $lists = $this->lists($owner);

        return $listId === null || $listId === '' || $listId === 'default'
            ? ($lists->first(fn (Wishlist $list) => $list->isDefault()) ?? $lists->first())
            : $lists->first(fn (Wishlist $list) => $list->id() === $listId);
    }

    public function findOrFail(Owner $owner, ?string $listId = null): Wishlist
    {
        return $this->find($owner, $listId) ?? throw WishlistException::listNotFound();
    }

    /**
     * The list to change: the one asked for, or the default (made now if
     * the owner has none yet).
     */
    public function findOrCreate(Owner $owner, ?string $listId = null): Wishlist
    {
        if ($list = $this->find($owner, $listId)) {
            return $list;
        }

        if ($listId !== null && $listId !== '' && $listId !== 'default') {
            throw WishlistException::listNotFound();
        }

        return Wishlist::make($owner)->setDefault();
    }

    /**
     * Whether a reference is on the owner's list: the default list, a list
     * by ID, or any of their lists with "any".
     */
    public function has(Owner $owner, string $reference, ?string $listId = null): bool
    {
        if ($listId === 'any') {
            return $this->lists($owner)->contains(fn (Wishlist $list) => $list->has($reference));
        }

        return (bool) $this->find($owner, $listId)?->has($reference);
    }

    public function add(Owner $owner, string $reference, ?string $listId = null, array $data = []): Wishlist
    {
        $this->ensureAllowed($reference);

        $list = $this->findOrCreate($owner, $listId);

        if (! $list->has($reference) && $list->count() >= $this->itemLimit()) {
            throw WishlistException::tooManyItems($this->itemLimit());
        }

        $list->add($item = Item::make($reference, $this->itemData($data) + $this->savedPrice($list, $reference)));
        $this->save($list);

        ItemAdded::dispatch($list, $list->item($reference) ?? $item);

        return $list;
    }

    public function remove(Owner $owner, string $reference, ?string $listId = null): Wishlist
    {
        $list = $this->findOrFail($owner, $listId);

        if ($list->has($reference)) {
            $list->remove($reference);
            $this->save($list);

            ItemRemoved::dispatch($list, $reference);
        }

        return $list;
    }

    /**
     * Add the reference if it isn't on the list, take it off if it is.
     *
     * @return array{0: Wishlist, 1: bool} the list, and whether it's on it now
     */
    public function toggle(Owner $owner, string $reference, ?string $listId = null, array $data = []): array
    {
        if ($this->has($owner, $reference, $listId)) {
            return [$this->remove($owner, $reference, $listId), false];
        }

        return [$this->add($owner, $reference, $listId, $data), true];
    }

    public function clear(Owner $owner, ?string $listId = null): Wishlist
    {
        $list = $this->findOrFail($owner, $listId);
        $list->clear();
        $this->save($list);

        return $list;
    }

    public function create(Owner $owner, ?string $title = null): Wishlist
    {
        $lists = $this->lists($owner);

        if ($lists->count() >= $this->listLimit()) {
            throw WishlistException::tooManyLists($this->listLimit());
        }

        $list = Wishlist::make($owner, $this->title($title))->setDefault($lists->isEmpty());
        $this->save($list);

        return $list;
    }

    public function rename(Owner $owner, ?string $listId, string $title): Wishlist
    {
        $list = $this->findOrFail($owner, $listId);
        $list->setTitle($this->title($title));
        $this->save($list);

        return $list;
    }

    /**
     * Delete a list. When it was the default, the next oldest takes over.
     */
    public function delete(Owner $owner, ?string $listId): Wishlist
    {
        $list = $this->findOrFail($owner, $listId);

        $this->repository->delete($list);
        $this->forget($owner);

        if ($list->isDefault() && $next = $this->lists($owner)->first()) {
            $this->save($next->setDefault());
        }

        WishlistDeleted::dispatch($list);

        return $list;
    }

    public function makeDefault(Owner $owner, string $listId): Wishlist
    {
        $list = $this->findOrFail($owner, $listId);

        foreach ($this->lists($owner) as $other) {
            if ($other->isDefault() && $other->id() !== $list->id()) {
                $this->save($other->setDefault(false));
            }
        }

        $this->save($list->setDefault());

        return $list;
    }

    /**
     * A new share link for a list; the old one stops working.
     */
    public function regenerateShareKey(Owner $owner, ?string $listId = null): Wishlist
    {
        $list = $this->findOrFail($owner, $listId);
        $list->regenerateShareKey();
        $this->save($list);

        return $list;
    }

    /**
     * Add several references at once, as when copying a shared list. Those
     * that can't be added are skipped, and so is anything over the limit.
     *
     * @param  iterable<string>  $references
     * @return array{0: Wishlist, 1: int} the list, and how many were new
     */
    public function import(Owner $owner, iterable $references, ?string $listId = null): array
    {
        $list = $this->findOrCreate($owner, $listId);
        $added = 0;

        foreach ($references as $reference) {
            $reference = (string) $reference;

            if ($list->has($reference) || ! $this->allowed($reference) || $list->count() >= $this->itemLimit()) {
                continue;
            }

            $list->add(Item::make($reference, $this->savedPrice($list, $reference)));
            $added++;
        }

        if ($added > 0 || ! $list->createdAt()) {
            $this->save($list);
        }

        return [$list, $added];
    }

    /**
     * A guest has signed in: their default list's items join the user's
     * default list, and their other lists become the user's.
     */
    public function merge(Owner $guest, Owner $user): void
    {
        $guestLists = $this->lists($guest);

        if ($guestLists->isEmpty()) {
            return;
        }

        foreach ($guestLists as $list) {
            if ($list->isDefault()) {
                $target = $this->findOrCreate($user);

                foreach ($list->items() as $item) {
                    if ($target->count() < $this->itemLimit()) {
                        $target->add($item);
                    }
                }

                $this->save($target);
                $this->repository->delete($list);
            } else {
                $this->save($list->setOwner($user));
            }
        }

        $this->forget($guest);
        $this->forget($user);

        GuestListsMerged::dispatch($guest, $user);
    }

    /**
     * Whether a reference may go on a list. See allows().
     */
    public function allowed(string $reference): bool
    {
        $data = Reference::find($reference);

        return $this->allows($data) && app(Cargo::class)->variantAllowed($reference, $data);
    }

    /**
     * Whether data may be on a list: what a visitor could open on the site.
     * A published entry in an allowed collection, or a term in an allowed
     * taxonomy. With collections left as null, only entries with a page
     * count; naming a collection opts its entries in, routed or not.
     * Scheduled and expired entries count as unpublished, and anything
     * behind Statamic's protect is left out, so neither can be read by ID.
     */
    public function allows(mixed $data): bool
    {
        if ($data instanceof Protectable && $data->getProtectionScheme()) {
            return false;
        }

        if ($data instanceof Entry) {
            $collections = config('wishlist.collections');

            return $data->status() === 'published'
                && ($collections === null
                    ? $data->uri() !== null
                    : in_array($data->collectionHandle(), (array) $collections, true));
        }

        if ($data instanceof Term) {
            return in_array($data->taxonomyHandle(), (array) config('wishlist.taxonomies', []), true);
        }

        return false;
    }

    public function ensureAllowed(string $reference): void
    {
        if (! $this->allowed($reference)) {
            throw WishlistException::notAllowed($reference);
        }
    }

    /**
     * The values a visitor may keep with an item (wishlist.item_fields),
     * as short plain text, and quantity as a whole number of at least one.
     */
    public function itemData(array $data): array
    {
        return collect($data)
            ->only((array) config('wishlist.item_fields', []))
            ->filter(fn ($value) => is_scalar($value) && $value !== '')
            ->map(fn ($value, $key) => $key === 'quantity'
                ? max(1, min(9999, (int) $value))
                : Str::limit(static::plainText((string) $value), 1000, ''))
            ->all();
    }

    /**
     * With Cargo, a product's price when it's first saved, so a list can
     * show when it's cheaper. Set here, never taken from the visitor.
     */
    protected function savedPrice(Wishlist $list, string $reference): array
    {
        if ($list->has($reference)) {
            return [];
        }

        $price = app(Cargo::class)->price($reference);

        return $price === null ? [] : ['saved_price' => $price];
    }

    public function save(Wishlist $list): void
    {
        $this->repository->save($list->touch());
        $this->forget(Owner::tryFromString($list->owner()));

        WishlistSaved::dispatch($list);
    }

    public function forget(?Owner $owner = null): void
    {
        if ($owner === null) {
            $this->memo = [];
        } else {
            unset($this->memo[(string) $owner]);
        }
    }

    protected function title(?string $title): string
    {
        $title = Str::limit(static::plainText((string) $title), 100, '');

        return $title !== '' ? $title : config('wishlist.default_list_title', 'My Wishlist');
    }

    /**
     * Text a visitor wrote, without whole HTML tags. Unlike strip_tags(),
     * a lone "<" stays ("I <3 this"). Output is escaped anyway; this keeps
     * markup out of what's stored. resources/js/wishlist.js does the same.
     */
    public static function plainText(string $text): string
    {
        return trim(preg_replace('/<[^>]*>/', '', $text) ?? '');
    }

    protected function listLimit(): int
    {
        return (int) config('wishlist.limits.lists', 20);
    }

    public function itemLimit(): int
    {
        return (int) config('wishlist.limits.items', 500);
    }
}
