<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class OperationAccountLog extends Model
{
    protected $table = 'operation_account_log';

    protected $fillable = ['operation_account_id', 'content'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function operationAccount()
    {
        return $this->belongsTo(OperationAccount::class, 'operation_account_id');
    }
}
