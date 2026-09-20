<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model
{
    protected $table = 'payroll_items';

    protected $guarded = [];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'sales_commission' => 'decimal:2',
        'performance_bonus' => 'decimal:2',
        'project_bonus' => 'decimal:2',
        'management_bonus' => 'decimal:2',
        'allowance_amount' => 'decimal:2',
        'refund_clawback' => 'decimal:2',
        'attendance_deduction' => 'decimal:2',
        'social_security' => 'decimal:2',
        'housing_fund' => 'decimal:2',
        'personal_tax' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'calculation_detail' => 'array',
        'manager_confirmed_at' => 'datetime:Y-m-d H:i:s',
        'employee_confirmed_at' => 'datetime:Y-m-d H:i:s',
        'appeal_handled_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function sheet()
    {
        return $this->belongsTo(PayrollSheet::class, 'payroll_sheet_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function managerConfirmedBy()
    {
        return $this->belongsTo(User::class, 'manager_confirmed_by');
    }

    public function employeeConfirmedBy()
    {
        return $this->belongsTo(User::class, 'employee_confirmed_by');
    }

    public function appealHandledBy()
    {
        return $this->belongsTo(User::class, 'appeal_handled_by');
    }
}
