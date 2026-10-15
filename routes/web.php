<?php

use Illuminate\Support\Facades\Route;
use NineteenNinetyFour\Wishlist\Http\Controllers\SharedController;

if (config('wishlist.share.enabled', true)) {
    $route = trim(config('wishlist.share.route', 'wishlist/shared'), '/');

    Route::get($route.'/{key?}', SharedController::class)
        ->where('key', '[A-Za-z0-9]+')
        ->name('wishlist.shared');
}
