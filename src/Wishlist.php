<?php

namespace NineteenNinetyFour\Wishlist;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Statamic\Facades\File;
use Statamic\Facades\Stache;
use Statamic\Facades\YAML;

/**
 * A named list belonging to one owner. Items are keyed by their reference,
 * so the same entry is on a list at most once.
 */
class Wishlist
{
    protected string $id;

    protected string $owner;

    protected string $title;

    protected string $shareKey;

    protected bool $default = false;

    /** @var array<string, Item> */
    protected array $items = [];

    protected ?CarbonImmutable $createdAt = null;

    protected ?CarbonImmutable $updatedAt = null;

    protected ?string $initialPath = null;

    public static function make(Owner|string $owner, ?string $title = null): static
    {
        $wishlist = new static;
        $wishlist->id = (string) Str::uuid();
        $wishlist->owner = (string) $owner;
        $wishlist->title = $title ?: config('wishlist.default_list_title', 'My Wishlist');
        $wishlist->shareKey = static::newShareKey();

        return $wishlist;
    }

    /**
     * Build a list from what a driver stored, in the shape of fileData().
     */
    /**
     * Build a list from what a driver stored, in the shape of fileData().
     * A file can be edited by hand, so anything malformed is read as
     * empty or left out rather than breaking every list.
     */
    public static function fromArray(string $id, array $data): static
    {
        $text = fn ($value) => is_scalar($value) ? (string) $value : '';

        $wishlist = new static;
        $wishlist->id = $id;
        $wishlist->owner = $text($data['owner'] ?? '');
        $wishlist->title = $text($data['title'] ?? '') ?: config('wishlist.default_list_title', 'My Wishlist');
        $wishlist->shareKey = $text($data['share_key'] ?? '') ?: static::newShareKey();
        $wishlist->default = filter_var($data['default'] ?? false, FILTER_VALIDATE_BOOL);
        $wishlist->createdAt = static::date($data['created_at'] ?? null);
        $wishlist->updatedAt = static::date($data['updated_at'] ?? null);

        foreach (is_iterable($data['items'] ?? null) ? $data['items'] : [] as $item) {
            $item = $item instanceof Item ? $item : Item::tryFromArray($item);

            if ($item) {
                $wishlist->items[$item->reference] = $item;
            }
        }

        return $wishlist;
    }

    /**
     * A stored date, or null when there isn't one that can be read.
     */
    public static function date(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function newShareKey(): string
    {
        return Str::random(24);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function owner(): string
    {
        return $this->owner;
    }

    public function setOwner(Owner|string $owner): static
    {
        $this->owner = (string) $owner;

        return $this;
    }

    public function ownedBy(Owner $owner): bool
    {
        return $this->owner === (string) $owner;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function shareKey(): string
    {
        return $this->shareKey;
    }

    /**
     * A new key, so links shared before stop working.
     */
    public function regenerateShareKey(): static
    {
        $this->shareKey = static::newShareKey();

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->default;
    }

    public function setDefault(bool $default = true): static
    {
        $this->default = $default;

        return $this;
    }

    public function createdAt(): ?CarbonImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?CarbonImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Drivers call this as they save.
     */
    public function touch(): static
    {
        $now = CarbonImmutable::now();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;

        return $this;
    }

    /**
     * @return Collection<int, Item>
     */
    public function items(): Collection
    {
        return collect(array_values($this->items));
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function has(string $reference): bool
    {
        return isset($this->items[$reference]);
    }

    public function item(string $reference): ?Item
    {
        return $this->items[$reference] ?? null;
    }

    /**
     * Put an item on the list. One already there keeps its place and the
     * date it was added; any values sent with it are merged into its own.
     */
    public function add(Item $item): static
    {
        $existing = $this->items[$item->reference] ?? null;

        $this->items[$item->reference] = $existing ? $existing->withData($item->data) : $item;

        return $this;
    }

    public function remove(string $reference): static
    {
        unset($this->items[$reference]);

        return $this;
    }

    public function clear(): static
    {
        $this->items = [];

        return $this;
    }

    /**
     * What a file holds, and the shape fromArray() reads.
     */
    public function fileData(): array
    {
        return array_filter([
            'title' => $this->title,
            'owner' => $this->owner,
            'share_key' => $this->shareKey,
            'default' => $this->default ?: null,
            'created_at' => $this->createdAt?->toIso8601String(),
            'updated_at' => $this->updatedAt?->toIso8601String(),
            'items' => $this->items()->map->toArray()->all(),
        ], fn ($value) => $value !== null);
    }

    public function toArray(): array
    {
        return ['id' => $this->id] + $this->fileData() + ['default' => $this->default];
    }

    /*
    |--------------------------------------------------------------------------
    | Flat files (file driver)
    |--------------------------------------------------------------------------
    */

    public function initialPath(?string $path = null): static|string|null
    {
        if (func_num_args() === 0) {
            return $this->initialPath;
        }

        $this->initialPath = $path;

        return $this;
    }

    public function path(): string
    {
        return rtrim(Stache::store('wishlists')->directory(), '/').'/'.$this->id.'.yaml';
    }

    public function writeFile(): void
    {
        File::put($this->path(), YAML::dump($this->fileData()));
    }

    public function deleteFile(): void
    {
        File::delete($this->path());
    }
}
