<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Web Tree: Page becomes a self-referencing hierarchy (folders + pages)
 * instead of a flat list. Retires the block-based content column in favor
 * of a plain text field — the block editor (App\Experience\*) is removed
 * in the same change, see PageController/PageResource.
 *
 * slug moves from globally-unique to unique-within-parent — two different
 * folders can both contain a page called "about" now that the URL is the
 * full path, not the bare slug.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropUnique(['slug']);

            $table->foreignId('parent_id')->nullable()->after('id')->constrained('pages')->nullOnDelete();
            $table->string('type')->default('page')->after('title');
            $table->unsignedInteger('order')->default(0)->after('status');
            $table->text('content')->nullable()->after('order');
            $table->softDeletes();

            $table->unique(['parent_id', 'slug']);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('blocks');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->json('blocks')->nullable();
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropUnique(['parent_id', 'slug']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['type', 'order', 'content', 'deleted_at']);
            $table->unique('slug');
        });
    }
};
