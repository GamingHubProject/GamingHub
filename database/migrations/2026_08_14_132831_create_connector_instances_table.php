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
        Schema::create('connector_instances', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // rest | pelican
            $table->string('base_url');
            $table->text('credentials')->nullable();
            $table->string('status')->default('untested');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connector_instances');
    }
};
