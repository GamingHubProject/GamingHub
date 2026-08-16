<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Site-wide settings as a single JSON blob in a single row, the same
 * key/value-JSON idiom already used elsewhere in this codebase
 * (Theme.tokens, Provider.config) rather than one column per setting —
 * see SiteOption::current()/value().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_options', function (Blueprint $table) {
            $table->id();
            $table->json('values');
            $table->timestamps();
        });

        DB::table('site_options')->insert([
            'values' => json_encode([
                'site_name' => 'Gaming Hub',
                'site_description' => 'Game community platform',
                'site_url' => null,
                'timezone' => 'UTC',
                'admin_email' => null,
                'discord_webhook' => null,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_options');
    }
};
