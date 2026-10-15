<?php

namespace NineteenNinetyFour\Wishlist\Http\Controllers;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use NineteenNinetyFour\Wishlist\CurrentOwner;
use NineteenNinetyFour\Wishlist\Http\ReadsText;
use NineteenNinetyFour\Wishlist\Item;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Presenter;
use NineteenNinetyFour\Wishlist\ShareCodec;
use NineteenNinetyFour\Wishlist\Wishlist;
use NineteenNinetyFour\Wishlist\WishlistException;
use NineteenNinetyFour\Wishlist\Wishlists;
use Statamic\View\View;

/**
 * The front-end actions, at /!/wishlist/... Each answers JSON to fetch()
 * (Accept: application/json) and redirects back to a plain form post,
 * with a message flashed as "wishlist_success" or an error in the
 * "wishlist" error bag.
 */
class ActionsController
{
    use ReadsText;

    public function __construct(
        protected Wishlists $wishlists,
        protected CurrentOwner $owner,
        protected Presenter $presenter,
    ) {}

    /**
     * Everything the script needs to start: the driver, a CSRF token (so it
     * works on statically cached pages), and the visitor's lists.
     */
    public function state(): JsonResponse
    {
        $owner = $this->wishlists->storesOnServer() ? $this->owner->get() : null;

        return response()->json([
            'driver' => config('wishlist.driver'),
            'csrf' => csrf_token(),
            'signed_in' => $owner?->isUser() ?? false,
            'lists' => $owner ? $this->wishlists->lists($owner)->map(fn (Wishlist $list) => $this->presenter->json($list))->values() : [],
        ])->header('Cache-Control', 'no-store, private');
    }

    /**
     * Fields of each reference (wishlist.resolve_fields), for scripts that
     * draw lists themselves. References come as ids[] or an encoded "i".
     */
    public function resolve(Request $request): JsonResponse
    {
        return response()->json([
            'items' => array_values($this->presenter->resolve($this->references($request), $this->wishlists)),
        ]);
    }

    /**
     * A partial rendered with the given items: how the browser driver shows
     * a list with the site's own templates. The partial's name is signed by
     * the tag that asked for it, so only that partial can be rendered.
     */
    public function render(Request $request): Response
    {
        try {
            $partial = Crypt::decryptString((string) $this->text($request, 'view', 4000));
        } catch (DecryptException) {
            abort(400);
        }

        // With the file and database drivers, the visitor's own list (so
        // the notes and quantities kept with each item come too). With the
        // browser driver, the items the script sent: posted with what's kept
        // with them, or as references in the URL.
        $owner = $this->wishlists->storesOnServer() ? $this->owner->get() : null;
        $list = $owner ? $this->wishlists->find($owner, $this->text($request, 'list', 64)) : null;

        $data = $list ? $this->presenter->list($list) : $this->linkData($request);

        $html = app(View::class)->template($partial)->with($data)->render();

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * A list drawn from the items in a link (the browser driver's).
     */
    protected function linkData(Request $request): array
    {
        $items = $this->presenter->items($this->linkItems($request)
            ->filter(fn (Item $item) => $this->wishlists->allowed($item->reference)));

        return [
            'items' => $items,
            'count' => count($items),
            'no_results' => count($items) === 0,
            // From the link, so anyone could have written it.
            'title' => e($this->linkTitle($request) ?? config('wishlist.default_list_title', 'My Wishlist')),
        ];
    }

    /**
     * The items to draw, from the browser. Posted ones may bring what the
     * visitor kept with them, held to the same rules as when saving
     * (wishlist.item_fields, plain text, quantity a whole number).
     *
     * @return Collection<int, Item>
     */
    protected function linkItems(Request $request): Collection
    {
        $posted = $request->isMethod('post') ? $request->input('items') : null;

        if (! is_array($posted)) {
            return collect($this->references($request))->map(fn (string $reference) => Item::make($reference));
        }

        return collect($posted)
            ->take((int) config('wishlist.limits.items', 500))
            ->map(function ($item) {
                $id = is_array($item) && (is_string($item['id'] ?? null) || is_int($item['id'] ?? null)) ? (string) $item['id'] : '';

                if ($id === '' || strlen($id) > 255) {
                    return null;
                }

                return Item::make($id, $this->wishlists->itemData(is_array($item['data'] ?? null) ? $item['data'] : []));
            })
            ->filter()
            ->unique(fn (Item $item) => $item->reference)
            ->values();
    }

    public function script(): Response
    {
        return response(file_get_contents(__DIR__.'/../../../resources/js/wishlist.js'))
            ->header('Content-Type', 'application/javascript; charset=UTF-8')
            // {{ wishlist:scripts }} adds the file's hash to the URL, so a
            // new version is a new URL and this one never changes.
            ->header('Cache-Control', 'public, max-age=31536000, immutable');
    }

    public function add(Request $request)
    {
        return $this->act($request, function (Owner $owner) use ($request) {
            $list = $this->wishlists->add($owner, $this->reference($request), $this->text($request, 'list', 64), $this->itemData($request));

            return [$list, __('wishlist::messages.added', ['list' => $list->title()]), ['added' => true]];
        });
    }

    public function remove(Request $request)
    {
        return $this->act($request, function (Owner $owner) use ($request) {
            $list = $this->wishlists->remove($owner, $this->reference($request), $this->text($request, 'list', 64));

            return [$list, __('wishlist::messages.removed', ['list' => $list->title()]), ['added' => false]];
        });
    }

    public function toggle(Request $request)
    {
        return $this->act($request, function (Owner $owner) use ($request) {
            [$list, $added] = $this->wishlists->toggle($owner, $this->reference($request), $this->text($request, 'list', 64), $this->itemData($request));

            return [$list, __($added ? 'wishlist::messages.added' : 'wishlist::messages.removed', ['list' => $list->title()]), ['added' => $added]];
        });
    }

    public function createList(Request $request)
    {
        return $this->act($request, function (Owner $owner) use ($request) {
            $list = $this->wishlists->create($owner, $this->text($request, 'title'));

            return [$list, __('wishlist::messages.created', ['list' => $list->title()])];
        });
    }

    public function renameList(Request $request)
    {
        return $this->act($request, function (Owner $owner) use ($request) {
            $list = $this->wishlists->rename($owner, $this->text($request, 'list', 64), (string) $this->text($request, 'title'));

            return [$list, __('wishlist::messages.renamed', ['list' => $list->title()])];
        });
    }

    public function deleteList(Request $request)
    {
        return $this->act($request, function (Owner $owner) use ($request) {
            $list = $this->wishlists->delete($owner, $this->text($request, 'list', 64));

            return [null, __('wishlist::messages.deleted', ['list' => $list->title()])];
        });
    }

    public function clearList(Request $request)
    {
        return $this->act($request, function (Owner $owner) use ($request) {
            $list = $this->wishlists->clear($owner, $this->text($request, 'list', 64));

            return [$list, __('wishlist::messages.cleared', ['list' => $list->title()])];
        });
    }

    public function defaultList(Request $request)
    {
        return $this->act($request, function (Owner $owner) use ($request) {
            $list = $this->wishlists->makeDefault($owner, (string) $this->text($request, 'list', 64));

            return [$list, null];
        });
    }

    public function shareList(Request $request)
    {
        return $this->act($request, function (Owner $owner) use ($request) {
            $list = $this->wishlists->regenerateShareKey($owner, $this->text($request, 'list', 64));

            return [$list, __('wishlist::messages.share_reset', ['list' => $list->title()])];
        });
    }

    /**
     * Copy references onto a list: "Save to my wishlist" on a shared page.
     * They come as ids[], an encoded "i", or a share key as "key".
     */
    public function import(Request $request)
    {
        return $this->act($request, function (Owner $owner) use ($request) {
            $key = $this->text($request, 'key', 64);

            $references = $key !== null
                ? ($this->wishlists->repository()->findByShareKey($key)?->items()->map->reference->all() ?? throw WishlistException::listNotFound())
                : $this->references($request);

            [$list, $added] = $this->wishlists->import($owner, $references, $this->text($request, 'list', 64));

            return [$list, __('wishlist::messages.imported', ['count' => $added, 'list' => $list->title()]), ['imported' => $added]];
        });
    }

    /**
     * Run a change for the current owner (made now for a guest) and answer
     * as JSON or with a redirect.
     *
     * @param  Closure(Owner): array{0: ?Wishlist, 1: ?string, 2?: array}  $change
     */
    protected function act(Request $request, Closure $change): JsonResponse|RedirectResponse
    {
        try {
            if (! $this->wishlists->storesOnServer()) {
                throw WishlistException::browserDriver();
            }

            $owner = $this->owner->get(create: true) ?? throw WishlistException::noOwner();

            [$list, $message, $extra] = array_pad($change($owner), 3, []);
        } catch (WishlistException $e) {
            return $this->failed($request, $e);
        }

        if ($request->wantsJson()) {
            return response()->json(array_merge([
                'success' => true,
                'message' => $message,
                'list' => $list ? $this->presenter->json($list) : null,
                'lists' => $this->wishlists->lists($owner)->map(fn (Wishlist $list) => $this->presenter->json($list))->values(),
            ], $extra));
        }

        return $this->back($request, '_redirect')->with('wishlist_success', $message);
    }

    protected function failed(Request $request, WishlistException $e): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->status);
        }

        return $this->back($request, '_error_redirect')->withErrors(['wishlist' => $e->getMessage()], 'wishlist')->withInput();
    }

    /**
     * Back to where the form was, or to a "_redirect" on this site.
     */
    protected function back(Request $request, string $field): RedirectResponse
    {
        $to = (string) $this->text($request, $field);

        $local = $to !== '' && (str_starts_with($to, '/') && ! str_starts_with($to, '//')
            || parse_url($to, PHP_URL_HOST) === $request->getHost());

        return $local ? redirect()->to($to) : redirect()->back();
    }

    protected function reference(Request $request): string
    {
        $reference = $this->text($request, 'id', 255);

        if ($reference === null) {
            throw new WishlistException(__('wishlist::messages.not_allowed'));
        }

        return $reference;
    }

    /**
     * Values to keep with an item, from data[...] or fields of the same
     * name at the top of a form.
     */
    protected function itemData(Request $request): array
    {
        $fields = (array) config('wishlist.item_fields', []);

        $data = $request->input('data', []);

        return array_merge($request->only($fields), is_array($data) ? $data : []);
    }

    /**
     * @return array<int, string>
     */
    protected function references(Request $request): array
    {
        $limit = (int) config('wishlist.share.max_items', 200);

        if (is_array($request->input('ids'))) {
            return collect($request->input('ids'))
                ->filter(fn ($id) => is_string($id) && $id !== '')
                ->unique()->take($limit)->values()->all();
        }

        return ShareCodec::decode($this->text($request, 'i', 20000), $limit);
    }
}
