<?php

namespace Finxnz\PlayerCounter\Filament\Admin\Resources;

use Finxnz\PlayerCounter\Filament\Admin\Resources\GameQueryResource\Pages\ManageGameQueries;
use Finxnz\PlayerCounter\Models\GameQuery;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GameQueryResource extends Resource
{
    protected static ?string $model = GameQuery::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-device-desktop-search';
    
    protected static ?int $navigationSort = 999;

    public static function getNavigationLabel(): string
    {
        return 'Game Queries';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('query_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('query_port_offset')
                    ->label('Port Offset')
                    ->placeholder('No offset'),
                TextColumn::make('servers.name')
                    ->label('Servers')
                    ->placeholder('No servers')
                    ->icon('tabler-server')
                    ->badge(),
            ])
            ->emptyStateIcon('tabler-device-desktop-search')
            ->emptyStateHeading('No Game Queries')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('query_type')
                    ->label('Type')
                    ->required()
                    ->options([
                        'minecraft' => 'Minecraft',
                    ])
                    ->default('minecraft')
                    ->native(false),
                TextInput::make('query_port_offset')
                    ->label('Port Offset')
                    ->placeholder('No offset')
                    ->numeric()
                    ->nullable()
                    ->minValue(1)
                    ->maxValue(64511),
                Select::make('servers')
                    ->label('Servers')
                    ->relationship('servers', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('query_type')
                    ->label('Type'),
                TextEntry::make('query_port_offset')
                    ->label('Port Offset')
                    ->placeholder('No offset'),
                TextEntry::make('eggs.name')
                    ->label('Eggs')
                    ->placeholder('No eggs')
                    ->badge()
                    ->columnSpanFull(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGameQueries::route('/'),
        ];
    }
}