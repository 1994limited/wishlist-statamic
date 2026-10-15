<?php

namespace NineteenNinetyFour\Wishlist;

use InvalidArgumentException;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Facades\User;

/**
 * Whose a list is: a user ("user:ID") or a guest's cookie ("guest:TOKEN").
 * Stored on each list as that string.
 */
final class Owner
{
    private function __construct(
        public readonly string $type,
        public readonly string $id,
    ) {}

    public static function user(UserContract|string $user): self
    {
        return new self('user', $user instanceof UserContract ? (string) $user->id() : $user);
    }

    public static function guest(string $token): self
    {
        return new self('guest', $token);
    }

    /**
     * The owner a stored string names, or null when it doesn't name one
     * (a hand-edited file, say).
     */
    public static function tryFromString(?string $owner): ?self
    {
        try {
            return self::fromString((string) $owner);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public static function fromString(string $owner): self
    {
        [$type, $id] = array_pad(explode(':', $owner, 2), 2, '');

        if (! in_array($type, ['user', 'guest'], true) || $id === '') {
            throw new InvalidArgumentException("[{$owner}] is not a wishlist owner.");
        }

        return new self($type, $id);
    }

    public function isGuest(): bool
    {
        return $this->type === 'guest';
    }

    public function isUser(): bool
    {
        return $this->type === 'user';
    }

    public function toUser(): ?UserContract
    {
        return $this->isUser() ? User::find($this->id) : null;
    }

    public function equals(self $other): bool
    {
        return (string) $this === (string) $other;
    }

    public function __toString(): string
    {
        return "{$this->type}:{$this->id}";
    }
}
