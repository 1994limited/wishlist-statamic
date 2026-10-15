# PHP API

For your own code: controllers, listeners, commands. Everything here is for the file and database drivers. With the browser driver the server has no lists.

## The `Wishlists` service

`NineteenNinetyFour\Wishlist\Wishlists` does everything a visitor can do, with the same rules: what can be saved, the limits, and which item fields are kept. Every change through it fires [events](#events).

```php
use NineteenNinetyFour\Wishlist\CurrentOwner;
use NineteenNinetyFour\Wishlist\Owner;
use NineteenNinetyFour\Wishlist\Wishlists;

$wishlists = app(Wishlists::class);

// Whose lists: a user, a guest's token, or whoever is visiting now.
$owner = Owner::user($user);                    // a user object or ID
$owner = Owner::guest($token);
$owner = app(CurrentOwner::class)->get();       // null if nobody can own lists here

$wishlists->lists($owner);                      // Collection of Wishlist, default first
$wishlists->find($owner);                       // the default list, or null
$wishlists->find($owner, $listId);              // one of theirs, or null
$wishlists->has($owner, $entryId);              // on the default list? ($listId or 'any' as a third argument)

$wishlists->add($owner, $entryId, $listId = null, ['notes' => 'Size M']);
$wishlists->remove($owner, $entryId, $listId = null);
[$list, $added] = $wishlists->toggle($owner, $entryId);
$wishlists->clear($owner, $listId);
$wishlists->create($owner, 'Birthday');
$wishlists->rename($owner, $listId, 'Birthday ideas');
$wishlists->delete($owner, $listId);
$wishlists->makeDefault($owner, $listId);
$wishlists->regenerateShareKey($owner, $listId);
[$list, $count] = $wishlists->import($owner, [$id1, $id2], $listId = null);
$wishlists->merge($guestOwner, $userOwner);

$wishlists->allowed($entryId);                  // could it be saved? (and, with Cargo, is the variant the product's)
```

A list ID of `null` means the owner's default list. `add`, `toggle` and `import` make it if they have none. A rule broken (an entry that can't be saved, a limit, someone else's list) throws `NineteenNinetyFour\Wishlist\WishlistException`, whose message is safe to show and whose `status` is the HTTP status to answer with.

## A list

`NineteenNinetyFour\Wishlist\Wishlist`:

```php
$list->id();
$list->title();
$list->owner();            // 'user:jo' or 'guest:…'
$list->ownedBy($owner);
$list->isDefault();
$list->shareKey();
$list->createdAt();        // CarbonImmutable
$list->updatedAt();
$list->count();
$list->has($entryId);
$list->items();            // Collection of Item
$list->item($entryId);     // Item or null
```

`NineteenNinetyFour\Wishlist\Item` has `reference` (the entry's ID, `taxonomy::slug` for a term, or `entry-id#variant` for a [Cargo variant](cargo.md#variants)), `addedAt` and `data` (the item fields, plus `saved_price` and `saved_currency` for Cargo products), and `resolve()` for the entry itself (or null if it's gone).

`NineteenNinetyFour\Wishlist\Reference` reads references: `Reference::base($ref)` (the entry's ID), `Reference::variant($ref)` (the variant's key, or null), `Reference::make($id, $variant)` and `Reference::find($ref)` (the entry or term).

The share link: `app(\NineteenNinetyFour\Wishlist\Presenter::class)->shareUrl($list)`.

## Cargo

`NineteenNinetyFour\Wishlist\Integrations\Cargo` is what the tags use with [Cargo](cargo.md):

```php
use NineteenNinetyFour\Wishlist\Integrations\Cargo;

$cargo = app(Cargo::class);

$cargo->installed();                 // a supported Cargo is installed
$cargo->price($reference);           // the price now, in pence or cents, or null
$cargo->describe($item, $entry);     // the item's `cargo` values, or null if it isn't a product
$cargo->addToCart($list);            // ['added' => 2, 'skipped' => [references]]
```

## The repository

To read across every visitor (for a report, an export, an email), use the repository. It reads and writes lists as they are, without the service's rules or events:

```php
use NineteenNinetyFour\Wishlist\Contracts\WishlistRepository;

$repository = app(WishlistRepository::class);

$repository->all();                          // every list
$repository->find($id);
$repository->findByShareKey($key);
$repository->forOwner($owner);
$repository->paginate(perPage: 25, page: 1, search: 'birthday');
$repository->popular(10);                    // [reference => number of lists], most first (Cargo variants count apart)
$repository->save($list);
$repository->delete($list);
```

For example, everyone who saved an entry (in any variant):

```php
$owners = $repository->all()
    ->filter(fn ($list) => $list->items()->contains(fn ($item) => Reference::base($item->reference) === $entry->id()))
    ->map(fn ($list) => Owner::fromString($list->owner()))
    ->filter(fn ($owner) => $owner->isUser())
    ->map(fn ($owner) => $owner->toUser())
    ->filter();
```

## Events

All in `NineteenNinetyFour\Wishlist\Events`, fired by the `Wishlists` service (so by every visitor action):

| Event | Properties | When |
| --- | --- | --- |
| `ItemAdded` | `wishlist`, `item` | An entry is saved, or its item fields change. |
| `ItemRemoved` | `wishlist`, `reference` | An entry is unsaved. |
| `WishlistSaved` | `wishlist` | Any change to a list, including the above. |
| `WishlistDeleted` | `wishlist` | A visitor deletes a list. |
| `GuestListsMerged` | `guest`, `user` (both `Owner`) | A guest signed in and their lists became the user's. |

```php
use Illuminate\Support\Facades\Event;
use NineteenNinetyFour\Wishlist\Events\ItemAdded;
use NineteenNinetyFour\Wishlist\Owner;

Event::listen(function (ItemAdded $event) {
    $owner = Owner::fromString($event->wishlist->owner());

    if ($user = $owner->toUser()) {
        // e.g. remember to email them if the price drops
    }
});
```

Deleting from the Control Panel, `wishlist:prune` and `wishlist:transfer` work on the repository directly, so they fire no events.

## Your own driver

To keep lists somewhere else (Redis, an API), implement `NineteenNinetyFour\Wishlist\Contracts\WishlistRepository` and bind it in a service provider:

```php
use NineteenNinetyFour\Wishlist\Contracts\WishlistRepository;

public function register(): void
{
    $this->app->singleton(WishlistRepository::class, MyWishlistRepository::class);
}
```

Return `true` from `storesOnServer()`. Build lists with `Wishlist::fromArray($id, $data)` and read them back with `$list->toArray()`. The addon's own tests run the same checks against both built-in drivers (`tests/Feature/RepositoryContract.php`): use that trait in a test of your own to check yours.
