<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class OperationAccount extends Model
{
    public const STATUS_ENABLED = 'enabled';

    public const STATUS_DISABLED = 'disabled';

    public const STATUS_FROZEN = 'frozen';

    protected $table = 'operation_account';

    protected $fillable = [
        'platform',
        'name',
        'platform_account_id',
        'device_carrier',
        'bound_phone_card',
        'phone_card_owner',
        'other_information',
        'person_in_charge',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function logs()
    {
        return $this->hasMany(OperationAccountLog::class, 'operation_account_id');
    }
}
