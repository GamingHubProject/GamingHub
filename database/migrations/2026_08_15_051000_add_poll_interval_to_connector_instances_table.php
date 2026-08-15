<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connector_instances', function (Blueprint $table) {
            $table->unsignedInteger('poll_interval_seconds')->default(30)->after('test_endpoint');
            $table->timestamp('last_polled_at')->nullable()->after('poll_interval_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('connector_instances', function (Blueprint $table) {
            $table->dropColumn('poll_interval_seconds');
        });
    }
};
