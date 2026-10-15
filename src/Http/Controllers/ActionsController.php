<?php

namespace NineteenNinetyFour\Wishlist\Http\Controllers;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use NineteenNinetyFour\Wishlist\CurrentOwner;
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
            $partial = Crypt::decryptString((string) $request->query('view'));
        } catch (DecryptException) {
            abort(400);
        }

        $items = $this->presenter->items(collect($this->references($request))
            ->filter(fn (string $reference) => $this->wishlists->allowed($reference))
            ->map(fn (string $reference) => Item::make($reference)));

        $html = app(View::class)->template($partial)->with([
            'items' => $items,
            'count' => count($items),
            'no_results' => count($items) === 0,
            'title' => $request->query('t'),
        ])->render();

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
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
            $list = $this->wishlists->add($owner, $this->reference($request), $request->input('list'), $this->itemData($request));

            return [$list, __('wishlist::messages.added', ['list' => $list->title()]), ['added' => true]];
        });
    }

    public function remove(Request $request)
    {
        return $this->act($request, function (Owner $owner) use ($request) {
            $list = $this->wishlists->remove($owner, $this->reference($request), $request->input('list'));

            return [$list, __('wishlist::messages.removed', ['list' => $list->title()]), ['added' => false]];
        });
    }

    public function toggle(Request $request)
    {
        return $this->act($request, function (Owner $owner) use ($request) {
            [$list, $added] = $this->wishlists->toggle($owner, $this->reference($request), $request->input('list'), $this->itemData($request));

            return [$list, __($added ? 'wishlist::messages.added' : 'wishlist::messages.removed', ['list' => $list->title()]), ['added' => $added]];
        });
    }

    public function createList(Request $request)
    {
        return $this->act($request, function (Owner $owner) use ($request) {
            $list = $this->wishlists->create($owner, $request->input('title'));

            return [$list, __('wishlist::messages.created', ['list' => $list->title()])];
        });
    }

    public function renameList(Request $request)
    {
        return $this->act($request, function (Owner $owner) use ($request) {
            $list = $this->wishlists->rename($owner, $request->input('list'), (string) $request->input('title'));

            return [$list, __('wishlist::messages.renamed', ['list' => $list->title()])];
        });
    }

    public function deleteList(Request $request)
    {
        return $this->act($request, function (Owner $owner) use ($request) {
            $list = $this->wishlists->delete($owner, $request->input('list'));

            return [null, __('wishlist::messages.deleted', ['list' => $list->title()])];
        });
    }

    public function clearList(Request $request)
    {
        return $this->act($request, function (Owner $owner) use ($request) {
            $list = $this->wishlists->clear($owner, $request->input('list'));

            return [$list, __('wishlist::messages.cleared', ['list' => $list->title()])];
        });
    }

    public function defaultList(Request $request)
    {
        return $this->act($request, function (Owner $owner) use ($request) {
            $list = $this->wishlists->makeDefault($owner, (string) $request->input('list'));

            return [$list, null];
        });
    }

    public function shareList(Request $request)
    {
        return $this->act($request, function (Owner $owner) use ($request) {
            $list = $this->wishlists->regenerateShareKey($owner, $request->input('list'));

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
            $references = $request->filled('key')
                ? ($this->wishlists->repository()->findByShareKey((string) $request->input('key'))?->items()->map->reference->all() ?? throw WishlistException::listNotFound())
                : $this->references($request);

            [$list, $added] = $this->wishlists->import($owner, $references, $request->input('list'));

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
        $to = (string) $request->input($field, '');

        $local = $to !== '' && (str_starts_with($to, '/') && ! str_starts_with($to, '//')
            || parse_url($to, PHP_URL_HOST) === $request->getHost());

        return $local ? redirect()->to($to) : redirect()->back();
    }

    protected function reference(Request $request): string
    {
        $reference = $request->input('id');

        if (! is_string($reference) || $reference === '') {
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

        return array_merge($request->only($fields), (array) $request->input('data', []));
    }

    /**
     * @return array<int, string>
     */
    protected function references(Request $request): array
    {
        $limit = (int) config('wishlist.share.max_items', 200);

        if ($request->has('ids')) {
            return collect((array) $request->input('ids'))
                ->filter(fn ($id) => is_string($id) && $id !== '')
                ->unique()->take($limit)->values()->all();
        }

        return ShareCodec::decode((string) $request->input('i', ''), $limit);
    }
}
