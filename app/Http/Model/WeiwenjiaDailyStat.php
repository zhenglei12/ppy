<?php
namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class WeiwenjiaDailyStat extends Model
{
    protected $table = 'crm_weiwenjia_daily_stats';
    protected $guarded = [];
    protected $casts = ['report_date' => 'date:Y-m-d', 'raw_payload' => 'array'];
}
