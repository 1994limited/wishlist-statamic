<?php

namespace NineteenNinetyFour\Wishlist;

use Statamic\Facades\Data;

/**
 * What a list item points at: an entry ID, "taxonomy::slug" for a term,
 * or, for a Cargo product with variants, "entry-id#variant-key". Each
 * variant is its own item, so a shirt in M and in L are two.
 */
final class Reference
{
    public const VARIANT = '#';

    public static function make(string $id, ?string $variant = null): string
    {
        $variant = $variant === null ? '' : trim($variant);

        return $variant === '' ? $id : $id.self::VARIANT.$variant;
    }

    /**
     * The entry or term's ID, without any variant.
     */
    public static function base(string $reference): string
    {
        return explode(self::VARIANT, $reference, 2)[0];
    }

    public static function variant(string $reference): ?string
    {
        $parts = explode(self::VARIANT, $reference, 2);

        return isset($parts[1]) && $parts[1] !== '' ? $parts[1] : null;
    }

    /**
     * The entry or term, or null when it's gone.
     */
    public static function find(string $reference): mixed
    {
        return Data::find(self::base($reference));
    }
}
