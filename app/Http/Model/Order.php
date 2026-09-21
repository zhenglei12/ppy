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
        'customer_legal_name',
        'customer_credit_code',
        'customer_mobile',
        'customer_wechat',
        'customer_industry',
        'product_type',
        'contract_no',
        'contract_amount',
        'payable_amount',
        'paid_amount',
        'receivable_amount',
        'payment_method',
        'payment_subject',
        'payment_due_date',
        'company_account',
        'invoice_required',
        'invoice_type',
        'invoice_title',
        'invoice_tax_no',
        'invoice_status',
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
        'kickoff_meeting_at',
        'sales_commitment',
        'ranking_commitment',
        'acquisition_commitment',
        'qualification_status',
        'case_authorized',
        'logo_authorized',
        'portrait_authorized',
        'complaint_history',
        'contract_files',
        'payment_voucher_files',
        'license_files',
        'authorization_files',
        'sales_handover_files',
        'approval_files',
        'submitted_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'contract_amount' => 'decimal:2',
        'payable_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'receivable_amount' => 'decimal:2',
        'payment_due_date' => 'date:Y-m-d',
        'invoice_required' => 'boolean',
        'next_action_at' => 'datetime:Y-m-d H:i:s',
        'expected_start_date' => 'date:Y-m-d',
        'actual_start_date' => 'date:Y-m-d',
        'expected_end_date' => 'date:Y-m-d',
        'kickoff_meeting_at' => 'datetime:Y-m-d H:i:s',
        'ranking_commitment' => 'boolean',
        'acquisition_commitment' => 'boolean',
        'case_authorized' => 'boolean',
        'logo_authorized' => 'boolean',
        'portrait_authorized' => 'boolean',
        'contract_files' => 'array',
        'payment_voucher_files' => 'array',
        'license_files' => 'array',
        'authorization_files' => 'array',
        'sales_handover_files' => 'array',
        'approval_files' => 'array',
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
