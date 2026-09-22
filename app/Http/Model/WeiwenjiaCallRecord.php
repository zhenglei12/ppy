<?php
namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class WeiwenjiaCallRecord extends Model
{
    protected $table = 'crm_weiwenjia_call_records';
    protected $guarded = [];
    protected $casts = ['through' => 'boolean', 'called_at' => 'datetime', 'raw_payload' => 'array'];
}
