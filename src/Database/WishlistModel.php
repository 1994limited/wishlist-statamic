<?php

namespace NineteenNinetyFour\Wishlist\Database;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WishlistModel extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function getConnectionName()
    {
        return config('wishlist.drivers.database.connection') ?: parent::getConnectionName();
    }

    public function getTable()
    {
        return config('wishlist.drivers.database.table', 'wishlists');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WishlistItemModel::class, 'wishlist_id')->orderBy('added_at')->orderBy('id');
    }
}
