<?php

namespace Finxnz\PlayerCounter\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $server_id
 * @property int $game_query_id
 */
class ServerGameQuery extends Pivot
{
    public $timestamps = false;

    protected $table = 'server_game_query';
}

