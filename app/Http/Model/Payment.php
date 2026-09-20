<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';

    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime:Y-m-d H:i:s', 'confirmed_at' => 'datetime:Y-m-d H:i:s'];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function plan()
    {
        return $this->belongsTo(PaymentPlan::class, 'payment_plan_id');
    }
}
