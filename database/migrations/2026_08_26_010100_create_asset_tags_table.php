<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Flat many-to-many, no pivot metadata — a tag means the same thing
        // regardless of which asset it's attached to, unlike folders which
        // are a single hierarchical placement.
        Schema::create('asset_asset_tag', function (Blueprint $table) {
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['asset_id', 'asset_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_asset_tag');
        Schema::dropIfExists('asset_tags');
    }
};
