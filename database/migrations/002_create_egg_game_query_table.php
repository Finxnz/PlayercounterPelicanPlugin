<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('egg_game_query')) {
            return;
        }

        Schema::create('egg_game_query', function (Blueprint $table) {
            $table->unsignedInteger('egg_id');
            $table->unsignedInteger('game_query_id');

            $table->unique('egg_id');

            if (Schema::hasTable('eggs')) {
                $table->foreign('egg_id')->references('id')->on('eggs')->cascadeOnDelete();
            }

            if (Schema::hasTable('game_queries')) {
                $table->foreign('game_query_id')->references('id')->on('game_queries')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_game_query');
    }
};
