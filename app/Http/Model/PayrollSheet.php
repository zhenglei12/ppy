<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class PayrollSheet extends Model
{
    protected $table = 'payroll_sheets';

    protected $guarded = [];

    protected $casts = [
        'period_month' => 'date:Y-m-d',
        'total_gross_amount' => 'decimal:2',
        'total_net_amount' => 'decimal:2',
        'calculated_at' => 'datetime:Y-m-d H:i:s',
        'submitted_at' => 'datetime:Y-m-d H:i:s',
        'finance_reviewed_at' => 'datetime:Y-m-d H:i:s',
        'admin_approved_at' => 'datetime:Y-m-d H:i:s',
        'locked_at' => 'datetime:Y-m-d H:i:s',
        'completed_at' => 'datetime:Y-m-d H:i:s',
        'paid_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function items()
    {
        return $this->hasMany(PayrollItem::class, 'payroll_sheet_id');
    }

    public function calculatedBy()
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function financeReviewedBy()
    {
        return $this->belongsTo(User::class, 'finance_reviewed_by');
    }

    public function adminApprovedBy()
    {
        return $this->belongsTo(User::class, 'admin_approved_by');
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
