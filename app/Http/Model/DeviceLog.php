<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class DeviceLog extends Model
{
    protected $table = 'device_log';

    protected $fillable = ['device_id', 'content'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id');
    }
}
