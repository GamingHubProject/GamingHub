<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ConfigurationPreset's only consumer was Instance's "apply preset" button
 * — with Instance gone (see gaming-hub-core's drop_instances_table
 * migration) it has no remaining purpose. Long-term plan: a Game
 * Extension owns its own settings shape and presets — extension work,
 * not a core table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('configuration_presets');
    }

    public function down(): void
    {
        Schema::create('configuration_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('values');
            $table->timestamps();
            $table->unique(['game_id', 'name']);
        });
    }
};
