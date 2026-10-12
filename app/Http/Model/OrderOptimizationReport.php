<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class OrderOptimizationReport extends Model
{
    protected $table = 'order_optimization_reports';

    protected $guarded = [];

    protected $casts = [
        'current_mention_rate' => 'decimal:2',
        'semi_monthly_report_at' => 'datetime:Y-m-d H:i:s',
        'data_report_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'publisher_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
