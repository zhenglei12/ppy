<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class OrderStageLog extends Model
{
    protected $table = 'order_stage_logs';

    protected $guarded = [];

    protected $casts = [
        'deadline_at' => 'datetime:Y-m-d H:i:s',
        'operated_at' => 'datetime:Y-m-d H:i:s',
    ];
}
