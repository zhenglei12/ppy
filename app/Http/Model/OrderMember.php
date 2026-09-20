<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class OrderMember extends Model
{
    protected $table = 'order_members';

    protected $guarded = [];

    protected $casts = [
        'commission_ratio' => 'decimal:4',
        'joined_at' => 'datetime:Y-m-d H:i:s',
        'left_at' => 'datetime:Y-m-d H:i:s',
    ];
}
