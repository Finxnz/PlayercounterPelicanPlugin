<?php

namespace Finxnz\PlayerCounter\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ServerGameQuery extends Pivot
{
    public $timestamps = false;

    protected $table = 'server_game_query';
}

