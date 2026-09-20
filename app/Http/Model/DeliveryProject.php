<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryProject extends Model
{
    use SoftDeletes;

    protected $table = 'delivery_projects';

    protected $guarded = [];

    protected $casts = ['baseline_data' => 'array', 'key_keywords' => 'array', 'planned_start_date' => 'date:Y-m-d', 'actual_start_date' => 'date:Y-m-d', 'planned_end_date' => 'date:Y-m-d', 'actual_end_date' => 'date:Y-m-d', 'renewal_warning_at' => 'datetime:Y-m-d H:i:s'];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function milestones()
    {
        return $this->hasMany(DeliveryMilestone::class, 'project_id')->orderBy('sequence_no');
    }

    public function tasks()
    {
        return $this->hasMany(DeliveryTask::class, 'project_id');
    }

    public function evidences()
    {
        return $this->hasMany(DeliveryEvidence::class, 'project_id');
    }
}
