<?php

namespace Finxnz\PlayerCounter\Filament\Admin\Resources\GameQueryResource\Pages;

use Finxnz\PlayerCounter\Filament\Admin\Resources\GameQueryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGameQueries extends ManageRecords
{
    protected static string $resource = GameQueryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}