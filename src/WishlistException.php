<?php

namespace NineteenNinetyFour\Wishlist;

use RuntimeException;

/**
 * Something a visitor asked for that can't be done. The message is safe to
 * show them; status is the HTTP status the action answers with.
 */
class WishlistException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public static function listNotFound(): self
    {
        return new self(__('wishlist::messages.list_not_found'), 404);
    }

    public static function notAllowed(string $reference): self
    {
        return new self(__('wishlist::messages.not_allowed', ['id' => $reference]));
    }

    public static function tooManyLists(int $limit): self
    {
        return new self(__('wishlist::messages.too_many_lists', ['limit' => $limit]));
    }

    public static function tooManyItems(int $limit): self
    {
        return new self(__('wishlist::messages.too_many_items', ['limit' => $limit]));
    }

    public static function noOwner(): self
    {
        return new self(__('wishlist::messages.sign_in'), 401);
    }

    public static function browserDriver(): self
    {
        return new self(__('wishlist::messages.browser_driver'), 400);
    }
}
