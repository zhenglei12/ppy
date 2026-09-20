<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryTask extends Model
{
    use SoftDeletes;

    protected $table = 'delivery_tasks';

    protected $guarded = [];

    protected $casts = ['due_at' => 'datetime:Y-m-d H:i:s', 'completed_at' => 'datetime:Y-m-d H:i:s'];

    public function project()
    {
        return $this->belongsTo(DeliveryProject::class, 'project_id');
    }

    public function milestone()
    {
        return $this->belongsTo(DeliveryMilestone::class, 'milestone_id');
    }

    public function evidences()
    {
        return $this->hasMany(DeliveryEvidence::class, 'task_id');
    }
}
