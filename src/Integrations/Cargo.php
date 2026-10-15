<?php

namespace NineteenNinetyFour\Wishlist\Integrations;

use Composer\InstalledVersions;
use DuncanMcClean\Cargo\Cart\Actions\AddToCart;
use DuncanMcClean\Cargo\Contracts\Products\Product;
use DuncanMcClean\Cargo\Facades\Cart;
use DuncanMcClean\Cargo\Facades\Product as Products;
use DuncanMcClean\Cargo\ServiceProvider;
use DuncanMcClean\Cargo\Support\Money;
use Illuminate\Validation\ValidationException;
use NineteenNinetyFour\Wishlist\Item;
use NineteenNinetyFour\Wishlist\Reference;
use NineteenNinetyFour\Wishlist\Wishlist;
use NineteenNinetyFour\Wishlist\Wishlists;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Site;
use Throwable;

/**
 * Wishlist with Cargo (duncanmcclean/statamic-cargo): products saved with
 * their variant, their price and stock in lists, the price when saved, and
 * adding a list to the basket with Cargo's own cart. It all switches off
 * when Cargo isn't installed.
 */
class Cargo
{
    /** The Cargo releases this works with: 1.15 up to (not including) 2.0. */
    public const MINIMUM_VERSION = '1.15.0';

    public const BEFORE_VERSION = '2.0.0';

    protected ?bool $supported = null;

    public function installed(): bool
    {
        return class_exists(ServiceProvider::class)
            && app()->providerIsLoaded(ServiceProvider::class)
            && $this->supported();
    }

    /**
     * Whether the installed Cargo is a version this was built for. An older
     * or newer major one is left alone, so the site carries on without the
     * integration rather than failing.
     */
    public function supported(?string $version = null): bool
    {
        if ($version === null && $this->supported !== null) {
            return $this->supported;
        }

        $version ??= class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('duncanmcclean/statamic-cargo')
            ? InstalledVersions::getVersion('duncanmcclean/statamic-cargo')
            : null;

        $supported = class_exists(AddToCart::class) && (
            // A development checkout: trust it if the classes are there.
            $version === null || ! preg_match('/^\d/', $version)
            || (version_compare($version, self::MINIMUM_VERSION, '>=') && version_compare($version, self::BEFORE_VERSION, '<'))
        );

        return func_num_args() > 0 ? $supported : $this->supported = $supported;
    }

    /**
     * The Cargo product an entry is, if it is one.
     */
    public function product(mixed $data): ?Product
    {
        if (! $this->installed() || ! $data instanceof Entry) {
            return null;
        }

        if (! in_array($data->collectionHandle(), (array) config('statamic.cargo.products.collections', ['products']), true)) {
            return null;
        }

        return $data instanceof Product ? $data : Products::find($data->id());
    }

    /**
     * Whether the reference's variant, if it names one, is one of the
     * product's. A reference without a variant is always fine.
     */
    public function variantAllowed(string $reference, mixed $data): bool
    {
        $variant = Reference::variant($reference);

        if ($variant === null) {
            return true;
        }

        $product = $this->product($data);

        return $product !== null && $product->isVariantProduct() && $product->variant($variant) !== null;
    }

    /**
     * The price, in the currency's smallest unit, of what the reference
     * names: the variant's, or the product's. Null for anything else, or a
     * variant product saved without choosing a variant.
     */
    public function price(string $reference, mixed $data = null): ?int
    {
        $product = $this->product($data ?? Reference::find($reference));

        if (! $product) {
            return null;
        }

        $variant = Reference::variant($reference);

        if ($variant !== null) {
            return $product->variant($variant)?->price();
        }

        return $product->isVariantProduct() || $this->choosesAmount($product) ? null : $product->price();
    }

    /**
     * A product the shopper names their own price for: a Gift Vouchers
     * (1994/gift-vouchers-statamic) voucher of any amount. It has no price
     * until they choose one on its page.
     */
    public function choosesAmount(Product $product): bool
    {
        return $product->get('voucher_amount_type') === 'open';
    }

    /**
     * What a list shows about a saved product: its price, variant, stock,
     * whether it can go in the basket, and how its price has moved since
     * it was saved. Null when it isn't a Cargo product.
     */
    public function describe(Item $item, mixed $data): ?array
    {
        $product = $this->product($data);

        if (! $product) {
            return null;
        }

        $key = Reference::variant($item->reference);
        $variant = $key !== null ? $product->variant($key) : null;
        $needsVariant = $product->isVariantProduct() && $variant === null;
        $needsAmount = ! $needsVariant && $this->choosesAmount($product);
        $needsChoice = $needsVariant || $needsAmount;

        $price = $needsChoice ? null : ($variant?->price() ?? $product->price());
        $stockEnabled = $variant ? $variant->isStockEnabled() : (bool) $product->isStockEnabled();
        $stock = $stockEnabled ? (int) ($variant ? $variant->stock() : $product->stock()) : null;

        $saved = is_numeric($item->data['saved_price'] ?? null) ? (int) $item->data['saved_price'] : null;

        // Saved in another site's currency: not comparable. Prices saved
        // before the currency was kept (1.2.0) only count on a shop with
        // one currency.
        $savedCurrency = $item->data['saved_currency'] ?? null;

        if ($saved !== null && ($savedCurrency !== null ? $savedCurrency !== $this->currency() : ! $this->singleCurrency())) {
            $saved = null;
        }
        $drop = $saved !== null && $price !== null && $price < $saved ? $saved - $price : null;

        return [
            'product' => $product->id(),
            'variant' => $variant?->key(),
            'variant_name' => $variant?->name(),
            'needs_variant' => $needsVariant,
            'needs_amount' => $needsAmount,
            // Something to choose on the product's page before it can go
            // in the basket: a variant, or an amount.
            'needs_choice' => $needsChoice,
            'price' => $price,
            'price_formatted' => $price !== null ? $this->money($price) : null,
            'stock' => $stock,
            'in_stock' => ! $needsChoice && ($stock === null || $stock > 0),
            'saved_price' => $saved,
            'saved_price_formatted' => $saved !== null ? $this->money($saved) : null,
            'price_dropped' => $drop !== null,
            'price_drop' => $drop,
            'price_drop_formatted' => $drop !== null ? $this->money($drop) : null,
        ];
    }

    /**
     * Put a list's products in the visitor's basket, with Cargo's own
     * rules (stock, prerequisites). What can't be added is left out.
     *
     * @return array{added: int, skipped: array<int, string>}
     */
    public function addToCart(Wishlist $list): array
    {
        $cart = Cart::current();
        $added = 0;
        $skipped = [];

        $wishlists = app(Wishlists::class);

        foreach ($list->items() as $item) {
            // Only what could still be saved: not unpublished, protected or
            // a variant that's gone since.
            if (! $wishlists->allowed($item->reference)) {
                $skipped[] = $item->reference;

                continue;
            }

            $product = $this->product(Reference::find($item->reference));
            $key = Reference::variant($item->reference);
            $variant = $product && $key !== null ? $product->variant($key) : null;

            if (! $product || ($product->isVariantProduct() && ! $variant) || (! $variant && $this->choosesAmount($product))) {
                $skipped[] = $item->reference;

                continue;
            }

            try {
                app(AddToCart::class)->handle($cart, $product, $variant, max(1, (int) ($item->data['quantity'] ?? 1)));
                $added++;
            } catch (ValidationException) {
                // Out of stock, or a product it needs first: leave it on the list.
                $skipped[] = $item->reference;
            } catch (Throwable $e) {
                report($e);
                $skipped[] = $item->reference;
            }
        }

        if ($added > 0) {
            $cart->save();
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    protected function singleCurrency(): bool
    {
        return Site::all()->map(fn ($site) => strtoupper((string) $site->attribute('currency')))->unique()->count() <= 1;
    }

    /**
     * The current site's currency code, as Cargo reads it.
     */
    public function currency(): ?string
    {
        $currency = Site::current()->attribute('currency');

        return is_string($currency) && $currency !== '' ? strtoupper($currency) : null;
    }

    public function money(int $amount): string
    {
        return Money::format($amount, Site::current());
    }
}
