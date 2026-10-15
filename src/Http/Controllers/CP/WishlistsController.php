<?php

namespace NineteenNinetyFour\Wishlist\Http\Controllers\CP;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use NineteenNinetyFour\Wishlist\Item;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Presenter;
use NineteenNinetyFour\Wishlist\Wishlist;
use NineteenNinetyFour\Wishlist\Wishlists;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\CP\Toast;
use Statamic\Facades\Data;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Content → Wishlists: every visitor's lists (file and database drivers),
 * what's on each, and the entries most often saved.
 */
class WishlistsController extends CpController
{
    public function __construct(Request $request, protected Wishlists $wishlists, protected Presenter $presenter)
    {
        parent::__construct($request);
    }

    public function index(Request $request): Response
    {
        $this->authorize('view wishlists');

        $search = trim((string) $request->query('search', '')) ?: null;
        $page = max(1, (int) $request->query('page', 1));
        $paginator = $this->wishlists->repository()->paginate(25, $page, $search);

        return Inertia::render('wishlist::Index', [
            'driver' => config('wishlist.driver'),
            'storesOnServer' => $this->wishlists->storesOnServer(),
            'search' => $search,
            'lists' => collect($paginator->items())->map(fn (Wishlist $list) => [
                'id' => $list->id(),
                'title' => $list->title(),
                'owner' => $this->owner($list),
                'count' => $list->count(),
                'updated_at' => $list->updatedAt()?->toIso8601String(),
                'updated_ago' => $list->updatedAt()?->diffForHumans(),
                'url' => cp_route('wishlist.show', $list->id()),
            ])->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'popular' => $this->wishlists->repository()->popular(10)->map(fn (int $count, string $reference) => array_merge(
                $this->describe($reference),
                ['count' => $count],
            ))->values()->all(),
            'urls' => ['index' => cp_route('wishlist.index')],
        ]);
    }

    public function show(string $wishlist): Response
    {
        $this->authorize('view wishlists');

        $list = $this->wishlists->repository()->find($wishlist) ?? abort(404);

        return Inertia::render('wishlist::Show', [
            'list' => [
                'id' => $list->id(),
                'title' => $list->title(),
                'owner' => $this->owner($list),
                'is_default' => $list->isDefault(),
                'count' => $list->count(),
                'created_at' => $list->createdAt()?->toDayDateTimeString(),
                'updated_at' => $list->updatedAt()?->toDayDateTimeString(),
                'share_url' => $this->presenter->shareUrl($list),
            ],
            'items' => $list->items()->map(fn (Item $item) => array_merge($this->describe($item->reference), [
                'added_at' => $item->addedAt->toDayDateTimeString(),
                'data' => $item->data,
            ]))->values()->all(),
            'canDelete' => User::current()->can('delete wishlists'),
            'urls' => [
                'index' => cp_route('wishlist.index'),
                'destroy' => cp_route('wishlist.destroy', $list->id()),
            ],
        ]);
    }

    public function destroy(string $wishlist): RedirectResponse
    {
        $this->authorize('delete wishlists');

        $list = $this->wishlists->repository()->find($wishlist) ?? abort(404);
        $this->wishlists->repository()->delete($list);

        Toast::success(__('Deleted :list.', ['list' => $list->title()]));

        return redirect()->to(cp_route('wishlist.index'));
    }

    /**
     * Who a list belongs to, as the CP shows it.
     */
    protected function owner(Wishlist $list): array
    {
        $owner = Owner::tryFromString($list->owner());
        $user = $owner?->toUser();

        return [
            'type' => $owner?->type ?? 'unknown',
            'name' => $user ? ($user->name() ?: $user->email()) : match (true) {
                $owner === null => __('Unknown owner'),
                $owner->isGuest() => __('Guest'),
                default => __('Deleted user'),
            },
            'email' => $user?->email(),
            'url' => $user ? $user->editUrl() : null,
        ];
    }

    /**
     * A reference as a row: its title and where to edit it, or that it has gone.
     */
    protected function describe(string $reference): array
    {
        $data = Data::find($reference);

        return [
            'id' => $reference,
            'title' => $data ? (string) ($data->get('title') ?? (method_exists($data, 'title') ? $data->title() : $reference)) : $reference,
            'missing' => $data === null,
            'kind' => $data instanceof Entry ? $data->collection()->title() : ($data ? __('Term') : null),
            'edit_url' => $data && method_exists($data, 'editUrl') ? $data->editUrl() : null,
            'url' => $data && method_exists($data, 'absoluteUrl') ? $data->absoluteUrl() : null,
        ];
    }
}
