<?php

namespace NineteenNinetyFour\Wishlist;

use Carbon\CarbonImmutable;
use Statamic\Facades\Data;

/**
 * One thing on a list: a reference to Statamic data (an entry ID, or
 * "taxonomy::slug" for a term), when it was added, and whatever values the
 * visitor kept with it (config: wishlist.item_fields).
 */
final class Item
{
    private mixed $resolved = false;

    public function __construct(
        public readonly string $reference,
        public readonly CarbonImmutable $addedAt,
        public readonly array $data = [],
    ) {}

    public static function make(string $reference, array $data = []): self
    {
        return new self($reference, CarbonImmutable::now(), $data);
    }

    public static function fromArray(array $item): self
    {
        return new self(
            (string) $item['id'],
            isset($item['added_at']) ? CarbonImmutable::parse($item['added_at']) : CarbonImmutable::now(),
            (array) ($item['data'] ?? []),
        );
    }

    public function withData(array $data): self
    {
        return new self($this->reference, $this->addedAt, array_merge($this->data, $data));
    }

    /**
     * The entry or term, or null when it has been deleted since.
     */
    public function resolve(): mixed
    {
        if ($this->resolved === false) {
            $this->resolved = Data::find($this->reference);
        }

        return $this->resolved;
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->reference,
            'added_at' => $this->addedAt->toIso8601String(),
            'data' => $this->data ?: null,
        ], fn ($value) => $value !== null);
    }

    public function __serialize(): array
    {
        return ['reference' => $this->reference, 'addedAt' => $this->addedAt, 'data' => $this->data];
    }

    public function __unserialize(array $data): void
    {
        $this->reference = $data['reference'];
        $this->addedAt = $data['addedAt'];
        $this->data = $data['data'];
        $this->resolved = false;
    }
}
