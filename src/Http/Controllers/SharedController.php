<?php

namespace NineteenNinetyFour\Wishlist\Http\Controllers;

use Illuminate\Http\Request;
use NineteenNinetyFour\Wishlist\CurrentOwner;
use NineteenNinetyFour\Wishlist\Http\ReadsText;
use NineteenNinetyFour\Wishlist\Item;
use NineteenNinetyFour\Wishlist\Presenter;
use NineteenNinetyFour\Wishlist\ShareCodec;
use NineteenNinetyFour\Wishlist\Wishlists;
use Statamic\View\View;

/**
 * The read-only page a share link opens, rendered with the site's template
 * and layout (wishlist.share). A link holds a list's share key (file and
 * database drivers) or the items themselves (browser driver).
 */
class SharedController
{
    use ReadsText;

    public function __construct(
        protected Wishlists $wishlists,
        protected CurrentOwner $owner,
        protected Presenter $presenter,
    ) {}

    public function __invoke(Request $request, ?string $key = null)
    {
        abort_unless(config('wishlist.share.enabled', true), 404);

        $data = $key === null ? $this->fromUrl($request) : $this->fromKey($key);

        $view = app(View::class)
            ->template(config('wishlist.share.template', 'wishlist::shared'))
            ->layout(config('wishlist.share.layout', 'layout'))
            ->with($data + ['shared_wishlist' => $data]);

        return response($view->render())
            ->header('X-Robots-Tag', 'noindex');
    }

    protected function fromKey(string $key): array
    {
        abort_unless($this->wishlists->storesOnServer(), 404);

        $list = $this->wishlists->repository()->findByShareKey($key) ?? abort(404);
        $owner = $this->owner->get();
        $items = $this->presenter->items($list->items());

        return [
            'title' => e($list->title()),
            'items' => $items,
            'count' => count($items),
            'no_results' => count($items) === 0,
            'is_owner' => $owner !== null && $list->ownedBy($owner),
            'share_key' => $key,
            'references' => $list->items()->map->reference->values()->all(),
            'encoded' => ShareCodec::encode($list->items()->map->reference),
        ];
    }

    protected function fromUrl(Request $request): array
    {
        $references = collect(ShareCodec::decode($this->text($request, 'i', 20000), (int) config('wishlist.share.max_items', 200)))
            ->filter(fn (string $reference) => $this->wishlists->allowed($reference))
            ->values();

        $items = $this->presenter->items($references->map(fn (string $reference) => Item::make($reference)));

        return [
            // From the link, so anyone could have written it.
            'title' => e($this->linkTitle($request) ?? config('wishlist.default_list_title', 'My Wishlist')),
            'items' => $items,
            'count' => count($items),
            'no_results' => count($items) === 0,
            'is_owner' => false,
            'share_key' => null,
            'references' => $references->all(),
            'encoded' => ShareCodec::encode($references),
        ];
    }
}
