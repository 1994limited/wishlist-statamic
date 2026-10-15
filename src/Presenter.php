<?php

namespace NineteenNinetyFour\Wishlist;

use NineteenNinetyFour\Wishlist\Integrations\Cargo;
use Statamic\Contracts\Data\Augmentable;

/**
 * Lists and items as tags, JSON and the Control Panel see them.
 */
class Presenter
{
    public function __construct(protected Wishlists $wishlists) {}

    /**
     * A list for Antlers: its own values, and items with all of each
     * entry's fields plus a "wishlist_item" with the visitor's values.
     */
    public function list(Wishlist $list): array
    {
        $items = $this->items($list->items());

        return [
            'id' => $list->id(),
            // What list forms inside a {{ wishlist:lists }} loop act on; "id"
            // could be the page's own ID.
            'wishlist_list_id' => $list->id(),
            // Visitors write titles and notes, so they're escaped for the
            // page, in text or in an attribute alike.
            'title' => e($list->title()),
            'is_default' => $list->isDefault(),
            'count' => count($items),
            'items_count' => count($items),
            'no_results' => count($items) === 0,
            'items' => $items,
            'share_url' => $this->shareUrl($list),
            'created_at' => $list->createdAt(),
            'updated_at' => $list->updatedAt(),
        ];
    }

    /**
     * Items whose entry or term still exists, each as the entry's augmented
     * values with "wishlist_item" alongside.
     *
     * @param  iterable<Item>  $items
     */
    public function items(iterable $items): array
    {
        $rows = [];

        foreach ($this->available($items) as $item) {
            $data = $item->resolve();

            $kept = array_map(fn ($value) => is_string($value) ? e($value) : $value, $item->data);

            $rows[] = array_merge($data->toAugmentedArray(), array_filter(['cargo' => app(Cargo::class)->describe($item, $data)]), [
                'wishlist_item' => [
                    'id' => $item->reference,
                    'added_at' => $item->addedAt,
                    'notes' => $kept['notes'] ?? null,
                    'quantity' => $kept['quantity'] ?? null,
                ] + $kept,
            ]);
        }

        return $rows;
    }

    /**
     * The items that can still be shown: ones that could still be added
     * (see Wishlists::allows()), so not deleted, unpublished, expired or
     * protected since, nor in a collection or taxonomy no longer allowed,
     * nor a Cargo variant the product no longer has.
     * Counts use this too, so they match the list.
     *
     * @param  iterable<Item>  $items
     * @return array<int, Item>
     */
    public function available(iterable $items): array
    {
        $available = [];

        foreach ($items as $item) {
            $data = $item->resolve();

            if ($data instanceof Augmentable && $this->wishlists->allows($data) && $this->variantStillThere($item, $data)) {
                $available[] = $item;
            }
        }

        return $available;
    }

    /**
     * A saved Cargo variant the product still has. Without a supported
     * Cargo there's nothing to check against, so saved items stay.
     */
    protected function variantStillThere(Item $item, mixed $data): bool
    {
        $cargo = app(Cargo::class);

        return ! $cargo->installed() || $cargo->variantAllowed($item->reference, $data);
    }

    /**
     * A list for JavaScript: the items that can still be shown, as
     * references and the visitor's values.
     */
    public function json(Wishlist $list): array
    {
        return [
            'id' => $list->id(),
            'title' => $list->title(),
            'default' => $list->isDefault(),
            'share_url' => $this->shareUrl($list),
            'items' => collect($this->available($list->items()))->map(fn (Item $item) => array_filter([
                'id' => $item->reference,
                'added_at' => $item->addedAt->toIso8601String(),
                'data' => $item->data ?: null,
            ]))->all(),
        ];
    }

    /**
     * The fields named in wishlist.resolve_fields for each reference that
     * still exists and may be listed, keyed by reference. For JavaScript.
     *
     * @param  iterable<string>  $references
     */
    public function resolve(iterable $references, Wishlists $wishlists): array
    {
        $fields = (array) config('wishlist.resolve_fields', ['title', 'url']);
        $resolved = [];

        foreach ($references as $reference) {
            if (! $wishlists->allowed($reference) || ! ($data = Reference::find($reference)) instanceof Augmentable) {
                continue;
            }

            $resolved[$reference] = ['id' => $reference] + $data->toAugmentedCollection($fields)->withShallowNesting()->toArray();
        }

        return $resolved;
    }

    public function shareUrl(Wishlist $list): ?string
    {
        if (! config('wishlist.share.enabled', true)) {
            return null;
        }

        return url(trim(config('wishlist.share.route', 'wishlist/shared'), '/').'/'.$list->shareKey());
    }

    /**
     * A browser driver share link: the references (and a title) in the URL.
     *
     * @param  iterable<string>  $references
     */
    public function browserShareUrl(iterable $references, ?string $title = null): string
    {
        $query = array_filter(['i' => ShareCodec::encode($references), 't' => $title]);

        return url(trim(config('wishlist.share.route', 'wishlist/shared'), '/')).'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
