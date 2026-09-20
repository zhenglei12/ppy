<?php

namespace App\Http\Services;

use App\Http\Model\Department;
use App\Http\Model\PayrollItem;
use App\Http\Model\PayrollSheet;
use App\Http\Model\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayrollService
{
    public const HR_FIELDS = ['base_salary', 'allowance_amount', 'attendance_deduction'];

    public const PERFORMANCE_FIELDS = ['sales_commission', 'performance_bonus', 'project_bonus', 'management_bonus'];

    public const FINANCE_FIELDS = [
        'sales_commission', 'performance_bonus', 'project_bonus', 'management_bonus', 'allowance_amount',
        'refund_clawback', 'attendance_deduction', 'social_security', 'housing_fund', 'personal_tax',
    ];

    public const ALL_AMOUNT_FIELDS = [
        'base_salary', 'sales_commission', 'performance_bonus', 'project_bonus', 'management_bonus',
        'allowance_amount', 'refund_clawback', 'attendance_deduction', 'social_security', 'housing_fund',
        'personal_tax',
    ];

    public function recalculateItem(PayrollItem $item): PayrollItem
    {
        $item->forceFill($this->calculateAmounts($item->only(self::ALL_AMOUNT_FIELDS)))->save();

        return $item;
    }

    public function calculateAmounts(array $values): array
    {
        $gross = $this->money($values['base_salary'] ?? 0)
            + $this->money($values['sales_commission'] ?? 0)
            + $this->money($values['performance_bonus'] ?? 0)
            + $this->money($values['project_bonus'] ?? 0)
            + $this->money($values['management_bonus'] ?? 0)
            + $this->money($values['allowance_amount'] ?? 0)
            - $this->money($values['refund_clawback'] ?? 0)
            - $this->money($values['attendance_deduction'] ?? 0);
        $gross = max(0.0, round($gross, 2));
        $net = max(0.0, round($gross
            - $this->money($values['social_security'] ?? 0)
            - $this->money($values['housing_fund'] ?? 0)
            - $this->money($values['personal_tax'] ?? 0), 2));

        return ['gross_salary' => $gross, 'net_salary' => $net];
    }

    public function syncSheetTotals(int $sheetId): PayrollSheet
    {
        $sheet = PayrollSheet::lockForUpdate()->findOrFail($sheetId);
        $sheet->update([
            'total_gross_amount' => PayrollItem::where('payroll_sheet_id', $sheetId)->sum('gross_salary'),
            'total_net_amount' => PayrollItem::where('payroll_sheet_id', $sheetId)->sum('net_salary'),
        ]);

        return $sheet->fresh();
    }

    public function snapshotUser(User $user): array
    {
        return [
            'employee_name' => $user->name,
            'department_name' => optional($user->department)->name,
            'position_name' => $user->position_name,
            'salary_plan_version' => $user->salary_plan_version,
            'base_salary' => $user->base_salary ?? 0,
        ];
    }

    public function applyItemScope(Builder $query): Builder
    {
        $ids = $this->visibleUserIds();

        return $ids === null ? $query : $query->whereIn('user_id', $ids);
    }

    /**
     * null 表示人事、财务、超级管理员可查看全量；其他用户仅返回本人或管理范围。
     */
    public function visibleUserIds(): ?array
    {
        $user = Auth::user();
        $aliases = $this->roleAliases();
        if (array_intersect($aliases, ['admin', 'hr', 'finance'])) {
            return null;
        }

        $ids = collect([$user->id]);
        $managerAliases = ['sales_director', 'sales_manager', 'technical_director'];
        $isManager = (bool) array_intersect($aliases, $managerAliases)
            || User::where('direct_manager_id', $user->id)->exists();
        if (! $isManager) {
            return $ids->all();
        }

        $departmentIds = $this->departmentTreeIds($user->department_id);
        $teamIds = User::query()
            ->where(function ($query) use ($departmentIds, $user) {
                if ($departmentIds) {
                    $query->whereIn('department_id', $departmentIds);
                }
                $query->orWhere('direct_manager_id', $user->id);
            })
            ->pluck('id');

        return $ids->merge($teamIds)->unique()->values()->all();
    }

    public function canManageUser(int $userId): bool
    {
        if ($userId === Auth::id()) {
            return false;
        }
        if ($this->isAdmin()) {
            return true;
        }
        $visible = $this->visibleUserIds();

        return $visible !== null && in_array($userId, $visible, true);
    }

    public function isAdmin(): bool
    {
        return Auth::user()->name === 'admin' || in_array('admin', $this->roleAliases(), true);
    }

    public function isPrivilegedViewer(): bool
    {
        return (bool) array_intersect($this->roleAliases(), ['admin', 'hr', 'finance']);
    }

    public function roleAliases(): array
    {
        return Auth::user()->roles->pluck('alias')->filter()->values()->all();
    }

    public function createApprovalCycle(PayrollSheet $sheet): int
    {
        return DB::table('approval_instances')->insertGetId([
            'approval_no' => 'PAYROLL-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6)),
            'business_type' => 'payroll',
            'business_id' => $sheet->id,
            'approval_type' => 'monthly_payroll',
            'title' => $sheet->period_month->format('Y年m月').'工资审批',
            'status' => 'pending',
            'current_step' => 1,
            'applicant_id' => Auth::id(),
            'submitted_at' => now(),
            'snapshot_data' => json_encode($sheet->load('items')->toArray(), JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function logApproval(PayrollSheet $sheet, int $step, string $stepName, string $action, string $comment = null): void
    {
        $instance = DB::table('approval_instances')
            ->where('business_type', 'payroll')
            ->where('business_id', $sheet->id)
            ->latest('id')
            ->first();
        if (! $instance || ($instance->status !== 'pending' && $action !== 'approved')) {
            $instanceId = $this->createApprovalCycle($sheet);
        } else {
            $instanceId = $instance->id;
        }

        DB::table('approval_records')->insert([
            'approval_instance_id' => $instanceId,
            'step_no' => $step,
            'step_name' => $stepName,
            'approver_id' => Auth::id(),
            'action' => $action,
            'comment' => $comment,
            'acted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('approval_instances')->where('id', $instanceId)->update([
            'current_step' => $step,
            'status' => $action === 'rejected' ? 'rejected' : 'pending',
            'finished_at' => $action === 'rejected' ? now() : null,
            'updated_at' => now(),
        ]);
    }

    public function finishApproval(PayrollSheet $sheet): void
    {
        DB::table('approval_instances')
            ->where('business_type', 'payroll')
            ->where('business_id', $sheet->id)
            ->where('status', 'pending')
            ->latest('id')
            ->limit(1)
            ->update(['status' => 'approved', 'finished_at' => now(), 'updated_at' => now()]);
    }

    private function departmentTreeIds($departmentId): array
    {
        if (! $departmentId) {
            return [];
        }
        $all = collect([(int) $departmentId]);
        $frontier = [(int) $departmentId];
        while ($frontier) {
            $children = Department::whereIn('parent_id', $frontier)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $frontier = array_values(array_diff($children, $all->all()));
            $all = $all->merge($frontier)->unique();
        }

        return $all->values()->all();
    }

    private function money($value): float
    {
        return round((float) ($value ?? 0), 2);
    }
}
