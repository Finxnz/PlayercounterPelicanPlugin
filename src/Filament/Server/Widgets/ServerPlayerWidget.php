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
            if (!Schema::hasTable('game_queries')) {
                return false;
            }

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

            return true;
        } catch (Exception $e) {
            try {
                report($e);
            } catch (Exception $reportException) {
            }
            return false;
        }
    }

    protected function getStats(): array
    {
        try {
            $server = Filament::getTenant();

            if (!$server || !$server->allocation) {
                return [];
            }

            if ($server->retrieveStatus()->isOffline()) {
                return [
                    SmallStatBlock::make('Server Status', 'Offline')
                        ->description('Server is currently offline')
                        ->color('warning'),
                ];
            }

            $gameQuery = PlayerCounterPlugin::getGameQuery($server)->first();

            if (!$gameQuery) {
                return [];
            }

            $data = $gameQuery->runQuery($server->allocation);

            if (empty($data)) {
                return [];
            }

            if (isset($data['query_error']) && $data['query_error'] === true) {
                $errorMsg = $data['error_message'] ?? 'Unknown error';
                \Log::error('[PlayerCounter] Widget showing error', ['error' => $errorMsg]);
                
                return [
                    SmallStatBlock::make('Query Error', 'Connection Failed')
                        ->description($errorMsg)
                        ->color('danger'),
                ];
            }

            return [
                SmallStatBlock::make(trans('player-counter::query.hostname'), $server->name),
                SmallStatBlock::make(trans('player-counter::query.players'), ($data['gq_numplayers'] ?? '?') . ' / ' . ($data['gq_maxplayers'] ?? '?')),
                SmallStatBlock::make(trans('player-counter::query.map'), $data['gq_mapname'] ?? trans('player-counter::query.unknown')),
            ];
        } catch (Exception $e) {
            \Log::error('[PlayerCounter] Widget exception', [
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            try {
                report($e);
            } catch (Exception $reportException) {
            }
            return [
                SmallStatBlock::make('Exception', 'Error Occurred')
                    ->description($e->getMessage())
                    ->color('danger'),
            ];
        }
    }
}
