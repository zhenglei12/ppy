<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    public const STATUS_IN_USE = 'in_use';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REPAIRING = 'repairing';

    public const STATUS_OUTBOUND = 'outbound';

    protected $table = 'device';

    protected $fillable = [
        'device_number',
        'model',
        'color',
        'memory',
        'purchase_date',
        'purchase_channel',
        'price',
        'holder',
        'status',
    ];

    protected $casts = [
        'purchase_date' => 'date:Y-m-d',
        'price' => 'decimal:2',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function logs()
    {
        return $this->hasMany(DeviceLog::class, 'device_id');
    }
}
