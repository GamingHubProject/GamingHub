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
        Schema::create('capability_bindings', function (Blueprint $table) {
            $table->id();
            $table->string('capability');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('provider')->default('manual');
            $table->json('value')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['capability', 'subject_type', 'subject_id'], 'capability_bindings_subject_unique');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capability_bindings');
    }
};
