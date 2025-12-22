<?php

namespace Finxnz\PlayerCounter;

use App\Models\Server;
use Finxnz\PlayerCounter\Models\GameQuery;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Exception;

class PlayerCounterPlugin implements Plugin
{
    public function getId(): string
    {
        return 'player-counter';
    }

    public function register(Panel $panel): void
    {
        try {
            $id = str($panel->getId())->title();

            $resourcePath = plugin_path($this->getId(), "src/Filament/$id/Resources");
            $resourceNamespace = "Finxnz\\PlayerCounter\\Filament\\$id\\Resources";

            $pagesPath = plugin_path($this->getId(), "src/Filament/$id/Pages");
            $pagesNamespace = "Finxnz\\PlayerCounter\\Filament\\$id\\Pages";

            $widgetsPath = plugin_path($this->getId(), "src/Filament/$id/Widgets");
            $widgetsNamespace = "Finxnz\\PlayerCounter\\Filament\\$id\\Widgets";

            if (is_dir($resourcePath)) {
                $panel->discoverResources($resourcePath, $resourceNamespace);
            }

            if (is_dir($pagesPath)) {
                $panel->discoverPages($pagesPath, $pagesNamespace);
            }

            if (is_dir($widgetsPath)) {
                $panel->discoverWidgets($widgetsPath, $widgetsNamespace);
            }
        } catch (Exception $e) {
            try {
                report($e);
            } catch (Exception $reportException) {
            }
        }
    }

    public function boot(Panel $panel): void {}

    public static function getGameQuery(Server $server): BelongsToMany
    {
        return $server->belongsToMany(GameQuery::class, 'server_game_query', 'server_id', 'game_query_id', 'uuid', 'id');
    }
}

