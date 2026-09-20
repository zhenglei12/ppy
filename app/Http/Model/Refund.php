<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $table = 'refunds';

    protected $guarded = [];

    protected $casts = ['refund_amount' => 'decimal:2', 'commission_clawback_amount' => 'decimal:2', 'approved_at' => 'datetime:Y-m-d H:i:s', 'refunded_at' => 'datetime:Y-m-d H:i:s'];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
