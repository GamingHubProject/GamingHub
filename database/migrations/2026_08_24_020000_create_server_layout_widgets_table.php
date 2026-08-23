<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Same shape as dashboard_widgets, deliberately — a separate table
        // rather than a polymorphic dashboard_widgets row so the two
        // completely different authorization models (private-owner vs.
        // public-read/admin-write) never have to share query/policy code.
        // config stays even though none of the initial 5 widget types use
        // it yet — server-banner is explicitly a placeholder pending Asset
        // Library, and will need it for a background image.
        Schema::create('server_layout_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_layout_id')->constrained()->cascadeOnDelete();
            $table->string('widget_type');
            $table->json('config')->nullable();
            $table->unsignedInteger('position_x')->default(0);
            $table->unsignedInteger('position_y')->default(0);
            $table->unsignedInteger('width')->default(6);
            $table->unsignedInteger('height')->default(4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_layout_widgets');
    }
};
