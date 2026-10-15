<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection()
    {
        return config('wishlist.drivers.database.connection') ?: parent::getConnection();
    }

    public function up(): void
    {
        $lists = config('wishlist.drivers.database.table', 'wishlists');
        $items = config('wishlist.drivers.database.items_table', 'wishlist_items');

        Schema::create($lists, function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('owner')->index();
            $table->string('title');
            $table->string('share_key', 64)->unique();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create($items, function (Blueprint $table) use ($lists) {
            $table->id();
            $table->foreignUuid('wishlist_id')->constrained($lists)->cascadeOnDelete();
            $table->string('reference')->index();
            $table->json('data')->nullable();
            $table->timestamp('added_at');
            $table->unique(['wishlist_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('wishlist.drivers.database.items_table', 'wishlist_items'));
        Schema::dropIfExists(config('wishlist.drivers.database.table', 'wishlists'));
    }
};
