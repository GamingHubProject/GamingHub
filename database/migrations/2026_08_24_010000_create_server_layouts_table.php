<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately not user-scoped: one row per server, shared by every
        // viewer of that server's detail page. No user_id at all — unlike
        // dashboard_pages (fully private, owner-only), read access here is
        // public and write access is gated by the Admin role, not identity.
        // See ServerLayoutController/ServerLayoutWidgetController.
        Schema::create('server_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_layouts');
    }
};
