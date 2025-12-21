<?php

namespace Finxnz\PlayerCounter\Filament\Server\Widgets;

use App\Filament\Server\Components\SmallStatBlock;
use App\Models\Server;
use Finxnz\PlayerCounter\Models\GameQuery;
use Finxnz\PlayerCounter\PlayerCounterPlugin;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\Schema;
use Exception;

class ServerPlayerWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        try {
            // Check if tables exist first
            if (!Schema::hasTable('game_queries')) {
                return false;
            }

            /** @var Server $server */
            $server = Filament::getTenant();

            if (!$server || !$server->allocation) {
                return false;
            }

            if ($server->isInConflictState()) {
                return false;
            }

            if (!PlayerCounterPlugin::getGameQuery($server)->exists()) {
                return false;
            }

            if ($server->retrieveStatus()->isOffline()) {
                return false;
            }

            return true;
        } catch (Exception $e) {
            // If any check fails, don't show the widget
            try {
                report($e);
            } catch (Exception $reportException) {
                // Ignore reporting errors
            }
            return false;
        }
    }

    protected function getStats(): array
    {
        try {
            /** @var Server $server */
            $server = Filament::getTenant();

            if (!$server || !$server->allocation) {
                return [];
            }

            /** @var ?GameQuery $gameQuery */
            $gameQuery = PlayerCounterPlugin::getGameQuery($server)->first();

            if (!$gameQuery) {
                return [];
            }

            $data = $gameQuery->runQuery($server->allocation);

            if (empty($data)) {
                return [];
            }

            return [
                SmallStatBlock::make(trans('player-counter::query.hostname'), $server->name),
                SmallStatBlock::make(trans('player-counter::query.players'), ($data['gq_numplayers'] ?? '?') . ' / ' . ($data['gq_maxplayers'] ?? '?')),
                SmallStatBlock::make(trans('player-counter::query.map'), $data['gq_mapname'] ?? trans('player-counter::query.unknown')),
            ];
        } catch (Exception $e) {
            // If stats retrieval fails, return empty array instead of crashing
            try {
                report($e);
            } catch (Exception $reportException) {
                // Ignore reporting errors
            }
            return [];
        }
    }
}
