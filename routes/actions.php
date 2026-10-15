<?php

use Illuminate\Support\Facades\Route;
use NineteenNinetyFour\Wishlist\Http\Controllers\ActionsController;

// At /!/wishlist/...

Route::get('state', [ActionsController::class, 'state'])->name('state');
Route::get('resolve', [ActionsController::class, 'resolve'])->name('resolve');
Route::get('render', [ActionsController::class, 'render'])->name('render');

// The browser driver posts its items (with their notes, too long for a
// URL). It only draws HTML and changes nothing, and that driver has no
// session to take a CSRF token from, so it's left out of the CSRF check.
Route::post('render', [ActionsController::class, 'render'])->name('render.post')->withoutMiddleware([
    'App\Http\Middleware\VerifyCsrfToken',
    'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
    'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken',
    'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
]);
Route::get('wishlist.js', [ActionsController::class, 'script'])->name('script');

// Changes are rate limited per visitor (wishlist.throttle), so a script
// can't fill the disk or database with lists.
Route::middleware(array_filter([config('wishlist.throttle') ? 'throttle:'.config('wishlist.throttle') : null]))->group(function () {
    Route::post('items/add', [ActionsController::class, 'add'])->name('items.add');
    Route::post('items/remove', [ActionsController::class, 'remove'])->name('items.remove');
    Route::post('items/toggle', [ActionsController::class, 'toggle'])->name('items.toggle');

    Route::post('lists/create', [ActionsController::class, 'createList'])->name('lists.create');
    Route::post('lists/rename', [ActionsController::class, 'renameList'])->name('lists.rename');
    Route::post('lists/delete', [ActionsController::class, 'deleteList'])->name('lists.delete');
    Route::post('lists/clear', [ActionsController::class, 'clearList'])->name('lists.clear');
    Route::post('lists/default', [ActionsController::class, 'defaultList'])->name('lists.default');
    Route::post('lists/share', [ActionsController::class, 'shareList'])->name('lists.share');
    Route::post('lists/import', [ActionsController::class, 'import'])->name('lists.import');

    // With Cargo: a list's products into the basket.
    Route::post('cart', [ActionsController::class, 'cart'])->name('cart');
});
