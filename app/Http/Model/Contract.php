<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $table = 'contracts';

    protected $guarded = [];

    protected $casts = ['contract_amount' => 'decimal:2', 'sign_date' => 'date:Y-m-d', 'start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d'];
}
