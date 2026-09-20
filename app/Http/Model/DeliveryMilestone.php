<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class DeliveryMilestone extends Model
{
    protected $table = 'delivery_milestones';

    protected $guarded = [];

    protected $casts = ['planned_at' => 'datetime:Y-m-d H:i:s', 'completed_at' => 'datetime:Y-m-d H:i:s'];

    public function project()
    {
        return $this->belongsTo(DeliveryProject::class, 'project_id');
    }
}
