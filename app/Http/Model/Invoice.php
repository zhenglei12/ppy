<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $table = 'invoices';

    protected $guarded = [];

    protected $casts = ['invoice_amount' => 'decimal:2', 'requested_at' => 'datetime:Y-m-d H:i:s', 'issued_at' => 'datetime:Y-m-d H:i:s'];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
