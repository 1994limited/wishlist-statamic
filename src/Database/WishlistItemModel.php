<?php

namespace NineteenNinetyFour\Wishlist\Database;

use Illuminate\Database\Eloquent\Model;

class WishlistItemModel extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'added_at' => 'immutable_datetime',
    ];

    public function getConnectionName()
    {
        return config('wishlist.drivers.database.connection') ?: parent::getConnectionName();
    }

    public function getTable()
    {
        return config('wishlist.drivers.database.items_table', 'wishlist_items');
    }
}
