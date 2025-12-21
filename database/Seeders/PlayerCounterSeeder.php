<?php

namespace Database\Seeders;

use App\Models\Egg;
use Finxnz\PlayerCounter\Models\EggGameQuery;
use Finxnz\PlayerCounter\Models\GameQuery;
use Illuminate\Database\Seeder;
use Exception;

class PlayerCounterSeeder extends Seeder
{
    public function run(): void
    {
        try {
            $minecraftQuery = GameQuery::firstOrCreate(['query_type' => 'minecraft']);
            $sourceQuery = GameQuery::firstOrCreate(['query_type' => 'source']);

            foreach (Egg::all() as $egg) {
                try {
                    $tags = $egg->tags ?? [];

                    if (in_array('minecraft', $tags)) {
                        EggGameQuery::firstOrCreate([
                            'egg_id' => $egg->id,
                        ], [
                            'game_query_id' => $minecraftQuery->id,
                        ]);
                    } elseif (in_array('source', $tags)) {
                        EggGameQuery::firstOrCreate([
                            'egg_id' => $egg->id,
                        ], [
                            'game_query_id' => $sourceQuery->id,
                        ]);
                    }
                } catch (Exception $e) {
                    // Skip this egg if there's an error
                    try {
                        $this->command->warn("Skipped egg {$egg->id}: " . $e->getMessage());
                    } catch (Exception $commandException) {
                        // Ignore if command output fails
                    }
                }
            }

            $this->command->info('Created game query types for minecraft and source');
        } catch (Exception $e) {
            try {
                $this->command->error('Seeder failed: ' . $e->getMessage());
            } catch (Exception $commandException) {
                // Ignore if command output fails
            }
        }
    }
}