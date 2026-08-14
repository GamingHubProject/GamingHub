<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('game_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('version');
            $table->string('status')->default('disabled');
            $table->text('description')->nullable();
            $table->json('manifest')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_extensions');
    }
};
