<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $table = 'crm_customers';

    protected $guarded = [];

    protected $casts = [
        'contact_authorized' => 'boolean',
        'material_authorized' => 'boolean',
        'next_follow_at' => 'datetime:Y-m-d H:i:s',
        'external_labels' => 'array',
        'external_payload' => 'array',
    ];

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'customer_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function coOwner()
    {
        return $this->belongsTo(User::class, 'co_owner_user_id');
    }
}
