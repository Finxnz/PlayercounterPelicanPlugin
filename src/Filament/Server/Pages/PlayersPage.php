<?php

namespace Finxnz\PlayerCounter\Filament\Server\Pages;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use App\Traits\Filament\BlockAccessInConflict;
use Finxnz\PlayerCounter\Filament\Server\Widgets\ServerPlayerWidget;
use Finxnz\PlayerCounter\Models\GameQuery;
use Finxnz\PlayerCounter\PlayerCounterPlugin;
use Carbon\CarbonInterval;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class PlayersPage extends Page implements HasTable
{
    use BlockAccessInConflict;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-users-group';

    protected static ?string $slug = 'players';

    protected static ?int $navigationSort = 30;

    public bool $queryError = false;
    
    public ?string $queryErrorMessage = null;

    public static function canAccess(): bool
    {
        try {
            if (!SchemaFacade::hasTable('game_queries')) {
                return false;
            }

            /** @var Server $server */
            $server = Filament::getTenant();

            return parent::canAccess() && $server->allocation && PlayerCounterPlugin::getGameQuery($server)->exists();
        } catch (Exception $e) {
            try {
                report($e);
            } catch (Exception $reportException) {
            }
            return false;
        }
    }

    public static function getNavigationLabel(): string
    {
        return trans('player-counter::query.players');
    }

    public static function getModelLabel(): string
    {
        return static::getNavigationLabel();
    }

    public static function getPluralModelLabel(): string
    {
        return static::getNavigationLabel();
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    /**
     * @throws Exception
     */
    public function table(Table $table): Table
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        /** @var ?GameQuery $gameQuery */
        $gameQuery = PlayerCounterPlugin::getGameQuery($server)->first();

        $isMinecraft = $gameQuery?->query_type === 'minecraft';

        $ops = [];

        if ($isMinecraft) {
            $fileRepository = (new DaemonFileRepository())->setServer($server);

            try {
                $opsContent = $fileRepository->getContent('ops.json');
                if ($opsContent) {
                    $ops = json_decode($opsContent, true, 512, JSON_THROW_ON_ERROR);
                    $ops = array_unique(array_map(fn ($data) => $data['name'] ?? '', $ops));
                    $ops = array_filter($ops);
                }
            } catch (Exception $exception) {
                try {
                    report($exception);
                } catch (Exception $reportException) {
                }
            }
        }

        return $table
            ->records(function (?string $search, int $page, int $recordsPerPage) {
                try {
                    /** @var Server $server */
                    $server = Filament::getTenant();

                    $players = [];
                    $queryError = false;
                    $queryErrorMessage = null;

                    /** @var ?GameQuery $gameQuery */
                    $gameQuery = PlayerCounterPlugin::getGameQuery($server)->first();

                    if ($gameQuery) {
                        $data = $gameQuery->runQuery($server->allocation);
                        
                        if (isset($data['query_error']) && $data['query_error'] === true) {
                            $queryError = true;
                            $queryErrorMessage = $data['error_message'] ?? 'Unknown error';
                            \Log::error('[PlayerCounter] Query failed in PlayersPage', [
                                'error_message' => $queryErrorMessage
                            ]);
                        } else {
                            $players = $data['players'] ?? [];
                        }
                    }

                    if (!is_array($players)) {
                        $players = [];
                    }

                    $this->queryError = $queryError;
                    $this->queryErrorMessage = $queryErrorMessage;

                    if ($search) {
                        $players = array_filter($players, fn ($player) => isset($player['player']) && str($player['player'])->contains($search, true));
                    }

                    return new LengthAwarePaginator(array_slice($players, ($page - 1) * $recordsPerPage, $recordsPerPage), count($players), $recordsPerPage, $page);
                } catch (Exception $e) {
                    \Log::error('[PlayerCounter] Exception in PlayersPage records', [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);
                    try {
                        report($e);
                    } catch (Exception $reportException) {
                    }
                    $this->queryError = true;
                    $this->queryErrorMessage = $e->getMessage();
                    return new LengthAwarePaginator([], 0, $recordsPerPage, $page);
                }
            })
            ->paginated([30, 60])
            ->contentGrid([
                'default' => 1,
                'lg' => 2,
                'xl' => 3,
            ])
            ->columns([
                Split::make([
                    ImageColumn::make('avatar')
                        ->visible(fn () => $isMinecraft)
                        ->state(fn (array $record) => 'https://cravatar.eu/helmhead/' . ($record['player'] ?? 'Steve') . '/256.png')
                        ->grow(false),
                    TextColumn::make('player')
                        ->label('Name')
                        ->searchable(),
                    TextColumn::make('is_op')
                        ->visible(fn () => $isMinecraft)
                        ->badge()
                        ->grow(false)
                        ->state(fn (array $record) => isset($record['player']) && in_array($record['player'], $ops) ? trans('player-counter::query.op') : null),
                    TextColumn::make('time')
                        ->hidden(fn () => $isMinecraft)
                        ->badge()
                        ->grow(false)
                        ->formatStateUsing(fn ($state) => $state ? CarbonInterval::seconds($state)->cascade()->forHumans() : null),
                ]),
            ])
            ->recordActions([
                Action::make('kick')
                    ->visible(fn () => $isMinecraft)
                    ->icon('tabler-door-exit')
                    ->color('danger')
                    ->action(function (array $record) {
                        /** @var Server $server */
                        $server = Filament::getTenant();

                        try {
                            $server->send('kick ' . $record['player']);

                            Notification::make()
                                ->title(trans('player-counter::query.notifications.player_kicked'))
                                ->body($record['player'])
                                ->success()
                                ->send();

                            $this->refreshPage();
                        } catch (Exception $exception) {
                            try {
                                report($exception);
                            } catch (Exception $reportException) {
                            }

                            Notification::make()
                                ->title(trans('player-counter::query.notifications.player_kick_failed'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('ban')
                    ->visible(fn () => $isMinecraft)
                    ->icon('tabler-ban')
                    ->color('danger')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('reason')
                            ->label(trans('player-counter::query.ban_reason'))
                            ->placeholder(trans('player-counter::query.ban_reason_placeholder'))
                            ->default('Banned by admin')
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('duration')
                            ->label(trans('player-counter::query.ban_duration'))
                            ->placeholder(trans('player-counter::query.ban_duration_placeholder'))
                            ->helperText(trans('player-counter::query.ban_duration_help'))
                            ->default(''),
                    ])
                    ->action(function (array $record, array $data) {
                        /** @var Server $server */
                        $server = Filament::getTenant();

                        try {
                            $command = 'ban ' . $record['player'];
                            
                            if (!empty($data['duration'])) {
                                $command = 'ban ' . $record['player'] . ' ' . $data['duration'];
                            }
                            
                            if (!empty($data['reason'])) {
                                $command .= ' ' . $data['reason'];
                            }

                            $server->send($command);

                            Notification::make()
                                ->title(trans('player-counter::query.notifications.player_banned'))
                                ->body($record['player'])
                                ->success()
                                ->send();

                            $this->refreshPage();
                        } catch (Exception $exception) {
                            try {
                                report($exception);
                            } catch (Exception $reportException) {
                            }

                            Notification::make()
                                ->title(trans('player-counter::query.notifications.player_ban_failed'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('op')
                    ->visible(fn () => $isMinecraft)
                    ->label(fn (array $record) => in_array($record['player'], $ops) ? trans('player-counter::query.remove_from_ops') : trans('player-counter::query.add_to_ops'))
                    ->icon(fn (array $record) => in_array($record['player'], $ops) ? 'tabler-shield-minus' : 'tabler-shield-plus')
                    ->color(fn (array $record) => in_array($record['player'], $ops) ? 'warning' : 'success')
                    ->action(function (array $record) use ($ops) {
                        /** @var Server $server */
                        $server = Filament::getTenant();

                        try {
                            $action = in_array($record['player'], $ops) ? 'deop' : 'op';

                            $server->send($action  . ' ' . $record['player']);

                            Notification::make()
                                ->title(trans('player-counter::query.notifications.player_' . $action))
                                ->body($record['player'])
                                ->success()
                                ->send();

                            $this->refreshPage();
                        } catch (Exception $exception) {
                            try {
                                report($exception);
                            } catch (Exception $reportException) {
                            }

                            Notification::make()
                                ->title(trans('player-counter::query.notifications.player_op_failed'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->emptyStateHeading(function () {
                /** @var Server $server */
                $server = Filament::getTenant();

                if ($server->retrieveStatus()->isOffline()) {
                    return trans('player-counter::query.table.server_offline');
                }

                if ($this->queryError) {
                    return trans('player-counter::query.table.query_failed');
                }

                return trans('player-counter::query.table.no_players');
            })
            ->emptyStateDescription(function () {
                /** @var Server $server */
                $server = Filament::getTenant();

                if ($server->retrieveStatus()->isOffline()) {
                    return null;
                }

                if ($this->queryError) {
                    $baseMessage = trans('player-counter::query.table.query_failed_description');
                    if ($this->queryErrorMessage) {
                        return $baseMessage . "\n\nError Details: " . $this->queryErrorMessage;
                    }
                    return $baseMessage;
                }

                return trans('player-counter::query.table.no_players_description');
            });
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ServerPlayerWidget::class,
        ];
    }

    private function refreshPage(): void
    {
        $url = self::getUrl();
        $this->redirect($url, FilamentView::hasSpaMode($url));
    }
}
