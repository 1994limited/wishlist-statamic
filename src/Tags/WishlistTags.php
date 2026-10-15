<?php

namespace NineteenNinetyFour\Wishlist\Tags;

use Illuminate\Support\Facades\Crypt;
use NineteenNinetyFour\Wishlist\CurrentOwner;
use NineteenNinetyFour\Wishlist\Integrations\Cargo;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Presenter;
use NineteenNinetyFour\Wishlist\Reference;
use NineteenNinetyFour\Wishlist\ShareCodec;
use NineteenNinetyFour\Wishlist\Wishlist;
use NineteenNinetyFour\Wishlist\Wishlists;
use Statamic\Tags\Concerns\RendersForms;
use Statamic\Tags\Tags;

/**
 * {{ wishlist }} and friends.
 *
 * With the file and database drivers these read the visitor's lists on the
 * server. With the browser driver the server knows nothing about them, so
 * lists read as empty and has/count as false/0; the script then fills in
 * anything marked with data-wishlist-* attributes, and wishlist:render
 * draws lists with your partial.
 */
class WishlistTags extends Tags
{
    use RendersForms;

    protected static $handle = 'wishlist';

    /**
     * {{ wishlist }}...{{ /wishlist }}: the default list, or list="id".
     */
    public function index()
    {
        return $this->list();
    }

    /**
     * {{ wishlist:list list="id" }}: one list's title, items, count, share_url.
     */
    public function list()
    {
        $list = $this->findList();

        return $list ? $this->presenter()->list($list) : [
            'id' => null,
            'title' => config('wishlist.default_list_title', 'My Wishlist'),
            'is_default' => true,
            'count' => 0,
            'items_count' => 0,
            'no_results' => true,
            'items' => [],
            'share_url' => null,
        ];
    }

    /**
     * {{ wishlist:lists }}: each of the visitor's lists, the default first.
     * With none, the contents are parsed once with no_results.
     */
    public function lists()
    {
        $owner = $this->currentOwner();

        $lists = $owner
            ? $this->wishlists()->lists($owner)->map(fn (Wishlist $list) => $this->presenter()->list($list))->values()->all()
            : [];

        return $lists ?: $this->parseNoResults();
    }

    /**
     * {{ wishlist:items list="id" }}: the items, each with the entry's
     * fields and wishlist_item (added_at, notes, quantity).
     */
    public function items()
    {
        $list = $this->findList();
        $items = $list ? $this->presenter()->items($list->items()) : [];

        if ($limit = $this->params->int('limit')) {
            $items = array_slice($items, 0, $limit);
        }

        return $items ?: $this->parseNoResults();
    }

    /**
     * {{ wishlist:has id="{{ id }}" }}: whether it's on the default list,
     * list="id", or list="any".
     */
    public function has(): bool
    {
        $owner = $this->currentOwner();
        $id = $this->reference();

        return $owner !== null && $id !== '' && $this->wishlists()->has($owner, $id, $this->params->get('list'));
    }

    public function inList(): bool
    {
        return $this->has();
    }

    /**
     * {{ wishlist:count }}: items on the default list, list="id", or
     * list="all" for every list together.
     */
    public function count(): int
    {
        $owner = $this->currentOwner();

        if (! $owner) {
            return 0;
        }

        $count = fn (?Wishlist $list) => $list ? count($this->presenter()->available($list->items())) : 0;

        if ($this->params->get('list') === 'all') {
            return $this->wishlists()->lists($owner)->sum($count);
        }

        return $count($this->findList());
    }

    public function shareUrl(): ?string
    {
        $list = $this->pickedList();

        return $list ? $this->presenter()->shareUrl($list) : null;
    }

    public function driver(): string
    {
        return (string) config('wishlist.driver');
    }

    public function storesOnServer(): bool
    {
        return $this->wishlists()->storesOnServer();
    }

    /**
     * {{ wishlist:success }}: the message after a form post, if any.
     */
    public function success(): ?string
    {
        $message = session('wishlist_success');

        // Messages name the list, and visitors write list titles.
        return is_string($message) ? e($message) : null;
    }

    /**
     * {{ wishlist:error }}: what went wrong with the last form post, if anything.
     */
    public function error(): ?string
    {
        $message = session('errors')?->getBag('wishlist')->first('wishlist');

        return $message ? e($message) : null;
    }

    /**
     * {{ wishlist:toggle id="{{ id }}" }}<button>{{ added ? 'Saved' : 'Save' }}</button>{{ /wishlist:toggle }}
     *
     * A form that works without JavaScript; with the script on the page it
     * is sent with fetch() instead and anything showing the item updates.
     */
    public function toggle(): string
    {
        return $this->itemForm('toggle');
    }

    public function add(): string
    {
        return $this->itemForm('add');
    }

    public function remove(): string
    {
        return $this->itemForm('remove');
    }

    /**
     * {{ wishlist:toggle_attrs id="{{ id }}" }} on a button of your own:
     * <button {{ wishlist:toggle_attrs id="{{ id }}" }}>Save</button>
     */
    public function toggleAttrs(): string
    {
        $id = $this->reference();
        $list = $this->params->get('list');

        return collect([
            'data-wishlist-toggle' => $id,
            'data-wishlist-list' => $list,
        ] + $this->stateAttributes($this->has()))->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value, $name) => $name.'="'.e($value).'"')
            ->implode(' ');
    }

    /**
     * {{ wishlist:create_list }}<input name="title">...{{ /wishlist:create_list }}
     */
    public function createList(): string
    {
        return $this->form('lists/create', [], ['data-wishlist-form' => 'lists/create']);
    }

    public function renameList(): string
    {
        return $this->listForm('lists/rename');
    }

    public function deleteList(): string
    {
        return $this->listForm('lists/delete');
    }

    public function clearList(): string
    {
        return $this->listForm('lists/clear');
    }

    public function makeDefault(): string
    {
        return $this->listForm('lists/default');
    }

    /**
     * {{ wishlist:reset_share_link list="id" }}: a new link; old ones stop working.
     */
    public function resetShareLink(): string
    {
        return $this->listForm('lists/share');
    }

    /**
     * {{ wishlist:import }} on a shared page: copies its items to the
     * visitor's own list. Reads share_key or encoded from the page.
     */
    public function import(): string
    {
        $fields = array_filter([
            'key' => $this->params->get('key', $this->context->get('share_key')),
            'i' => $this->params->get('i', $this->context->get('encoded')),
            'list' => $this->params->get('list'),
        ], fn ($value) => is_string($value) && $value !== '');

        return $this->form('lists/import', $fields, ['data-wishlist-form' => 'import']);
    }

    /**
     * {{ wishlist:render partial="partials/wishlist" list="id" }}
     *
     * Renders the partial with the list's items, count, title and
     * no_results, inside a container the script redraws when the list
     * changes. With the browser driver the server has no list to render,
     * so the script draws it on load; anything between the tags shows
     * until then.
     */
    public function render(): string
    {
        $partial = (string) $this->params->get('partial');
        $view = str_contains($partial, '::') ? $partial : 'partials/'.ltrim(preg_replace('#^partials/#', '', $partial), '/');
        $list = $this->pickedList();

        $html = $this->wishlists()->storesOnServer()
            ? view($view, $list ? $this->presenter()->list($list) : $this->list())->render()
            : (string) ($this->isPair ? $this->parse() : '');

        $attributes = collect([
            'data-wishlist-render' => Crypt::encryptString($view),
            'data-wishlist-list' => $this->params->get('list'),
            'class' => $this->params->get('class'),
        ])->filter()->map(fn ($value, $name) => $name.'="'.e($value).'"')->implode(' ');

        return '<div '.$attributes.'>'.$html.'</div>';
    }

    /**
     * {{ wishlist:scripts }} in <head>: the styles that show the right
     * data-wishlist-when label from the first paint, the script (deferred)
     * and its settings.
     */
    public function scripts(): string
    {
        $config = [
            'driver' => config('wishlist.driver'),
            'endpoint' => url('!/wishlist'),
            'shareUrl' => config('wishlist.share.enabled', true) ? url(trim(config('wishlist.share.route', 'wishlist/shared'), '/')) : null,
            'storageKey' => config('wishlist.drivers.browser.storage_key', 'statamic.wishlist'),
            'pickedCookie' => Wishlists::PICKED_COOKIE,
            'defaultTitle' => config('wishlist.default_list_title', 'My Wishlist'),
            'limits' => config('wishlist.limits'),
            'itemFields' => array_values((array) config('wishlist.item_fields', [])),
            'maxShareItems' => (int) config('wishlist.share.max_items', 200),
        ];

        $styles = '<style>'
            .'[data-wishlist-active="true"] [data-wishlist-when="not-added"],'
            .'[data-wishlist-active="false"] [data-wishlist-when="added"],'
            .':is([data-wishlist-toggle],form[data-wishlist-id]):not([data-wishlist-active]) [data-wishlist-when="added"]{display:none!important}'
            .':is([data-wishlist-toggle],form[data-wishlist-id]):not([data-wishlist-active]) [data-wishlist-when="not-added"],'
            .'select[data-wishlist-lists]:empty{visibility:hidden}'
            .'</style>';

        return $styles.'<script>window.WishlistConfig = '.json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES).';</script>'
            .'<script src="'.e(url('!/wishlist/wishlist.js').'?v='.substr(md5_file(__DIR__.'/../../resources/js/wishlist.js'), 0, 12)).'" defer></script>';
    }

    /**
     * The encoded form of the visitor's list, for links built by hand.
     */
    public function encoded(): string
    {
        $list = $this->findList();

        return $list ? ShareCodec::encode($list->items()->map->reference) : '';
    }

    protected function itemForm(string $action): string
    {
        $id = $this->reference();
        $added = $this->has();

        return $this->form("items/{$action}", array_filter([
            'id' => $id,
            'list' => $this->params->get('list'),
        ]), array_filter([
            'data-wishlist-form' => $action,
            'data-wishlist-id' => $id,
            'data-wishlist-active' => $this->stateAttributes($added)['data-wishlist-active'] ?? null,
        ]), ['added' => $added, 'in_list' => $added]);
    }

    /**
     * Whether an item is on the list, for the script and the styles in
     * {{ wishlist:scripts }}. Only the server drivers know when the page is
     * rendered; with the browser driver the attributes are left off, and
     * the labels stay hidden until the script has the list.
     */
    protected function stateAttributes(bool $added): array
    {
        if (! $this->wishlists()->storesOnServer()) {
            return [];
        }

        return [
            'data-wishlist-active' => $added ? 'true' : 'false',
            'aria-pressed' => $added ? 'true' : 'false',
        ];
    }

    /**
     * A form acting on one list: list="id", the list of the
     * {{ wishlist:lists }} loop it's in, or else the default (or, with the
     * script, the list picked in a [data-wishlist-lists] select).
     */
    protected function listForm(string $action): string
    {
        return $this->form($action, array_filter([
            'list' => $this->params->get('list', $this->context->get('wishlist_list_id')),
        ]), ['data-wishlist-form' => $action]);
    }

    /**
     * A form posting to an action, with hidden fields, and the tag's
     * contents parsed with $data.
     */
    protected function form(string $action, array $fields, array $attrs = [], array $data = []): string
    {
        $known = ['id', 'entry', 'variant', 'list', 'redirect', 'error_redirect', 'key', 'i', 'partial', 'csrf'];

        $html = $this->formOpen(url("!/wishlist/{$action}"), 'POST', $known, $attrs);

        $fields += array_filter([
            '_redirect' => $this->params->get('redirect'),
            '_error_redirect' => $this->params->get('error_redirect'),
        ]);

        foreach ($fields as $name => $value) {
            $html .= '<input type="hidden" name="'.e($name).'" value="'.e($value).'">';
        }

        $html .= $this->isPair ? $this->parse($data) : '';

        return $html.$this->formClose();
    }

    /**
     * What the tag is about: id="..." (or entry="..."), and with Cargo,
     * variant="..." for one of a product's variants.
     */
    protected function reference(): string
    {
        $id = (string) $this->params->get(['id', 'entry']);
        $variant = $this->params->get('variant');

        return $id === '' ? '' : Reference::make(Reference::base($id), is_scalar($variant) ? (string) $variant : Reference::variant($id));
    }

    /**
     * {{ wishlist:add_to_cart }}<button>Add all to basket</button>{{ /wishlist:add_to_cart }}
     *
     * With Cargo: a form that puts the list's products in the basket.
     * Without Cargo, nothing.
     */
    public function addToCart(): string
    {
        if (! app(Cargo::class)->installed()) {
            return '';
        }

        $list = $this->pickedList();

        return $this->form('cart', array_filter(['list' => $list?->id()]), ['data-wishlist-form' => 'cart']);
    }

    protected function findList(): ?Wishlist
    {
        $owner = $this->currentOwner();

        return $owner ? $this->wishlists()->find($owner, $this->params->get('list')) : null;
    }

    /**
     * The list named, or the one the visitor picked in a list picker (kept
     * in a cookie by the script), or the default. For what the script ties
     * to the picker: drawn lists, the share link, the basket form. So the
     * page arrives showing the picked list.
     */
    protected function pickedList(): ?Wishlist
    {
        $owner = $this->currentOwner();
        $picked = request()->cookie(Wishlists::PICKED_COOKIE);

        if ($owner && $this->params->get('list') === null && is_string($picked) && $picked !== '') {
            if ($list = $this->wishlists()->find($owner, substr($picked, 0, 64))) {
                return $list;
            }
        }

        return $this->findList();
    }

    protected function currentOwner(): ?Owner
    {
        return $this->wishlists()->storesOnServer() ? app(CurrentOwner::class)->get() : null;
    }

    protected function wishlists(): Wishlists
    {
        return app(Wishlists::class);
    }

    protected function presenter(): Presenter
    {
        return app(Presenter::class);
    }
}
