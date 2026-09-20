<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class DeliveryEvidence extends Model
{
    protected $table = 'delivery_evidences';

    protected $guarded = [];

    protected $casts = ['submitted_at' => 'datetime:Y-m-d H:i:s', 'reviewed_at' => 'datetime:Y-m-d H:i:s'];

    public function project()
    {
        return $this->belongsTo(DeliveryProject::class, 'project_id');
    }

    public function task()
    {
        return $this->belongsTo(DeliveryTask::class, 'task_id');
    }
}
