<?php


namespace App\Http\Model;


use Illuminate\Database\Eloquent\Model;

class DeviceManagement extends Model
{
    protected $table = 'device_management';

    public $fillable = [
        'equipment_number',
        'is_operation',
        'hold_operation',
        "name",
        'is_cal',
        'phone',
        'phone_name',
        'auth_name',
        'agent',
        'account_nature',
        'certification',
        'credit_code',
        'main_name',
        'account_id',
        'corp',
        'auth_time',
        'cause',
        'seal_time',
        'unset_time',
        'processing_scheme',
        'remark'
    ];

    protected $casts = [
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
