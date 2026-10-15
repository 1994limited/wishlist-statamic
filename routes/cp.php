<?php

use Illuminate\Support\Facades\Route;
use NineteenNinetyFour\Wishlist\Http\Controllers\CP\WishlistsController;

Route::prefix('wishlists')->name('wishlist.')->group(function () {
    Route::get('/', [WishlistsController::class, 'index'])->name('index');
    Route::get('{wishlist}', [WishlistsController::class, 'show'])->name('show');
    Route::delete('{wishlist}', [WishlistsController::class, 'destroy'])->name('destroy');
});
