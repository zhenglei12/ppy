<?php
namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class WeiwenjiaUser extends Model
{
    protected $table = 'crm_weiwenjia_users';
    protected $guarded = [];
    protected $casts = ['raw_payload' => 'array', 'external_updated_at' => 'datetime', 'synced_at' => 'datetime'];
}
