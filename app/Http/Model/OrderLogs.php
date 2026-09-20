<?php


namespace App\Http\Model;


use Illuminate\Database\Eloquent\Model;

class OrderLogs extends Model
{
    protected $table = 'order_logs';

    public $fillable = [
        'order_id',
        'remark',
        'url',
        'reason',
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
