<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_queries', function (Blueprint $table) {
            $table->integer('query_port_absolute')->nullable()->after('query_port_offset');
        });
    }

    public function down(): void
    {
        Schema::table('game_queries', function (Blueprint $table) {
            $table->dropColumn('query_port_absolute');
        });
    }
};
