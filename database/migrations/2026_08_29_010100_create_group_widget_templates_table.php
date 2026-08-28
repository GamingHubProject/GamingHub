<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A JSON snapshot, not relational rows — deliberately. The whole point of
 * a template is that placing it creates a fully independent copy (editing
 * one instance never affects another, or the template itself), so there's
 * no FK from here back to the page_layout_widgets rows it was captured
 * from. Placing a template just walks `snapshot` and creates brand new
 * widget rows; nothing is ever shared or referenced after that point. See
 * GroupWidgetTemplateController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_widget_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Shape: { width, height, children: [{ widget_type, config,
            // position_x, position_y, width, height }, ...] }
            $table->json('snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_widget_templates');
    }
};
