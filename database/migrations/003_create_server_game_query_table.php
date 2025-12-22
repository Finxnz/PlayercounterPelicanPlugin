<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('server_game_query')) {
            return;
        }

        Schema::create('server_game_query', function (Blueprint $table) {
            $table->char('server_id', 36);
            $table->unsignedInteger('game_query_id');

            $table->unique('server_id');

            if (Schema::hasTable('servers')) {
                $table->foreign('server_id')->references('uuid')->on('servers')->cascadeOnDelete();
            }

            if (Schema::hasTable('game_queries')) {
                $table->foreign('game_query_id')->references('id')->on('game_queries')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_game_query');
    }
};

