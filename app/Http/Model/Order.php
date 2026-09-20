<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $table = 'order';

    protected $fillable = [
        'order_no',
        'customer_id',
        'contact_id',
        'product_type',
        'product_name',
        'contract_amount',
        'discount_amount',
        'payable_amount',
        'paid_amount',
        'receivable_amount',
        'payment_terms',
        'sales_user_id',
        'sales_manager_id',
        'technical_director_id',
        'optimizer_id',
        'assistant_id',
        'current_stage',
        'business_status',
        'health_status',
        'owner_user_id',
        'next_action',
        'next_action_at',
        'expected_start_date',
        'actual_start_date',
        'service_cycle_days',
        'expected_end_date',
        'customer_owner_name',
        'primary_business',
        'target_region',
        'service_objective',
        'sales_commitment',
        'risk_summary',
        'submitted_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'contract_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'payable_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'receivable_amount' => 'decimal:2',
        'next_action_at' => 'datetime:Y-m-d H:i:s',
        'expected_start_date' => 'date:Y-m-d',
        'actual_start_date' => 'date:Y-m-d',
        'expected_end_date' => 'date:Y-m-d',
        'submitted_at' => 'datetime:Y-m-d H:i:s',
        'completed_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function salesUser()
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function salesManager()
    {
        return $this->belongsTo(User::class, 'sales_manager_id');
    }

    public function technicalDirector()
    {
        return $this->belongsTo(User::class, 'technical_director_id');
    }

    public function optimizer()
    {
        return $this->belongsTo(User::class, 'optimizer_id');
    }

    public function assistant()
    {
        return $this->belongsTo(User::class, 'assistant_id');
    }

    public function members()
    {
        return $this->hasMany(OrderMember::class, 'order_id');
    }

    public function stageLogs()
    {
        return $this->hasMany(OrderStageLog::class, 'order_id');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class, 'order_id');
    }

    public function paymentPlans()
    {
        return $this->hasMany(PaymentPlan::class, 'order_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    public function deliveryProject()
    {
        return $this->hasOne(DeliveryProject::class, 'order_id');
    }

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
