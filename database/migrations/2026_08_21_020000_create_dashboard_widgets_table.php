<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_page_id')->constrained()->cascadeOnDelete();
            // Matches a key in the frontend widget registry (see Priority 16
            // design's widget system) — not a foreign key, just a lookup
            // string the SPA uses to pick which component to render.
            $table->string('widget_type');
            $table->json('config')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};
