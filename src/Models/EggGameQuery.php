<?php

namespace Finxnz\PlayerCounter\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $egg_id
 * @property int $game_query_id
 */
class EggGameQuery extends Pivot
{
    public $timestamps = false;
}
