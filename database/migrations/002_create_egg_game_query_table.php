<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egg_game_query', function (Blueprint $table) {
            $table->unsignedInteger('egg_id');
            $table->foreign('egg_id')->references('id')->on('eggs')->cascadeOnDelete();

            $table->unsignedInteger('game_query_id');
            $table->foreign('game_query_id')->references('id')->on('game_queries')->cascadeOnDelete();

            $table->unique('egg_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_game_query');
    }
};
