<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class PaymentPlan extends Model
{
    protected $table = 'payment_plans';

    protected $guarded = [];

    protected $casts = ['planned_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'due_date' => 'date:Y-m-d', 'next_follow_at' => 'datetime:Y-m-d H:i:s'];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'payment_plan_id');
    }
}
