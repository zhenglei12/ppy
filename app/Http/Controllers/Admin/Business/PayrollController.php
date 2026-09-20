<?php

namespace App\Http\Controllers\Admin\Business;

use App\Http\Controllers\Controller;
use App\Http\Model\PayrollItem;
use App\Http\Model\PayrollSheet;
use App\Http\Model\User;
use App\Http\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayrollController extends Controller
{
    public function __construct(private PayrollService $payroll)
    {
    }

    public function sheets(Request $request)
    {
        $query = PayrollSheet::query()->with([
            'calculatedBy:id,name', 'submittedBy:id,name', 'financeReviewedBy:id,name',
            'adminApprovedBy:id,name', 'paidBy:id,name',
        ])->withCount('items');
        $visibleUserIds = $this->payroll->visibleUserIds();
        if ($visibleUserIds !== null) {
            $query->whereHas('items', fn (Builder $q) => $q->whereIn('user_id', $visibleUserIds));
        }
        $query->when($request->filled('period_month'), fn ($q) => $q->where('period_month', Carbon::parse($request->input('period_month'))->startOfMonth()->toDateString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')));

        $result = $query->latest('period_month')->paginate($request->integer('pageSize', 20));
        $result->getCollection()->transform(function (PayrollSheet $sheet) use ($visibleUserIds) {
            if ($visibleUserIds !== null) {
                $visibleItems = PayrollItem::where('payroll_sheet_id', $sheet->id)->whereIn('user_id', $visibleUserIds);
                $sheet->items_count = (clone $visibleItems)->count();
                $sheet->total_gross_amount = number_format((float) (clone $visibleItems)->sum('gross_salary'), 2, '.', '');
                $sheet->total_net_amount = number_format((float) $visibleItems->sum('net_salary'), 2, '.', '');
            }
            $sheet->setAttribute('available_actions', $this->sheetActions($sheet));

            return $sheet;
        });

        return $result;
    }

    public function sheetDetail(Request $request)
    {
        $request->validate(['id' => ['required', 'exists:payroll_sheets,id']]);
        $sheet = PayrollSheet::with([
            'calculatedBy:id,name', 'submittedBy:id,name', 'financeReviewedBy:id,name',
            'adminApprovedBy:id,name', 'lockedBy:id,name', 'paidBy:id,name',
        ])->findOrFail($request->integer('id'));
        $items = $this->payroll->applyItemScope(PayrollItem::query())
            ->where('payroll_sheet_id', $sheet->id)
            ->with($this->itemRelations())
            ->orderBy('id')
            ->get();
        abort_if($items->isEmpty() && ! $this->payroll->isPrivilegedViewer(), 403, '无权查看该工资表');
        $visibleUserIds = $this->payroll->visibleUserIds();
        if ($visibleUserIds !== null) {
            $sheet->total_gross_amount = number_format((float) $items->sum('gross_salary'), 2, '.', '');
            $sheet->total_net_amount = number_format((float) $items->sum('net_salary'), 2, '.', '');
        }
        $items->each(fn (PayrollItem $item) => $item->setAttribute('available_actions', $this->itemActions($item)));
        $sheet->setRelation('items', $items);
        $sheet->setAttribute('available_actions', $this->sheetActions($sheet));

        return $sheet;
    }

    public function createSheet(Request $request)
    {
        $data = $request->validate([
            'period_month' => ['required', 'date'],
            'formula_version' => ['required', 'string', 'max:32'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);
        $period = Carbon::parse($data['period_month'])->startOfMonth()->toDateString();
        abort_if(PayrollSheet::where('period_month', $period)->exists(), 422, '该月份工资表已创建，不能重复添加');

        return DB::transaction(function () use ($data, $period) {
            $sheet = PayrollSheet::create([
                'sheet_no' => 'PAY-'.Carbon::parse($period)->format('Ym'),
                'period_month' => $period,
                'formula_version' => $data['formula_version'],
                'status' => 'draft',
                'calculated_by' => Auth::id(),
                'calculated_at' => now(),
            ]);
            $users = User::query()->with('department')
                ->whereIn('employment_status', ['probation', 'active', 'transferred'])
                ->when(! empty($data['user_ids']), fn ($q) => $q->whereIn('id', $data['user_ids']))
                ->orderBy('id')->get();
            abort_if($users->isEmpty(), 422, '当前没有可生成工资的员工');

            foreach ($users as $user) {
                $item = PayrollItem::create(array_merge([
                    'payroll_sheet_id' => $sheet->id,
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'manager_status' => 'pending',
                    'calculation_detail' => ['hr_created_by' => Auth::id(), 'hr_created_at' => now()->toDateTimeString()],
                ], $this->payroll->snapshotUser($user)));
                $this->payroll->recalculateItem($item);
            }
            $this->payroll->syncSheetTotals($sheet->id);

            return $sheet->fresh()->load('items.user:id,name,department_id');
        });
    }

    public function updateSheet(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'exists:payroll_sheets,id'],
            'formula_version' => ['sometimes', 'required', 'string', 'max:32'],
        ]);
        $sheet = PayrollSheet::findOrFail($data['id']);
        $this->assertSheetStage($sheet, 'draft', '工资表已提交给下一角色，不能修改');
        $sheet->update(collect($data)->except('id')->all());

        return $sheet->fresh();
    }

    public function deleteSheet(Request $request)
    {
        $request->validate(['id' => ['required', 'exists:payroll_sheets,id']]);
        $sheet = PayrollSheet::findOrFail($request->integer('id'));
        $this->assertSheetStage($sheet, 'draft', '仅人事草稿可以删除');
        abort_if($sheet->items()->whereIn('status', ['confirmed', 'paid'])->exists(), 422, '已有员工确认的工资表不能删除');
        $sheet->delete();

        return ['deleted' => true];
    }

    public function items(Request $request)
    {
        $query = $this->payroll->applyItemScope(PayrollItem::query()->with($this->itemRelations()));
        $query->when($request->filled('payroll_sheet_id'), fn ($q) => $q->where('payroll_sheet_id', $request->integer('payroll_sheet_id')))
            ->when($request->filled('period_month'), fn ($q) => $q->whereHas('sheet', fn ($s) => $s->where('period_month', Carbon::parse($request->input('period_month'))->startOfMonth()->toDateString())))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('manager_status'), fn ($q) => $q->where('manager_status', $request->input('manager_status')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('keyword'), function ($q) use ($request) {
                $keyword = $request->input('keyword');
                $q->where(fn ($sub) => $sub->where('employee_name', 'like', "%{$keyword}%")->orWhere('department_name', 'like', "%{$keyword}%"));
            });

        $result = $query->latest('id')->paginate($request->integer('pageSize', 20));
        $result->getCollection()->each(fn (PayrollItem $item) => $item->setAttribute('available_actions', $this->itemActions($item)));

        return $result;
    }

    public function itemDetail(Request $request)
    {
        $request->validate(['id' => ['required', 'exists:payroll_items,id']]);

        $item = $this->scopedItem($request->integer('id'))->load($this->itemRelations());
        $item->setAttribute('available_actions', $this->itemActions($item));

        return $item;
    }

    public function createItem(Request $request)
    {
        $data = $request->validate(array_merge([
            'payroll_sheet_id' => ['required', 'exists:payroll_sheets,id'],
            'user_id' => ['required', 'exists:users,id'],
            'calculation_detail' => ['nullable', 'array'],
        ], $this->amountRules(PayrollService::HR_FIELDS)));

        return DB::transaction(function () use ($data) {
            $sheet = PayrollSheet::lockForUpdate()->findOrFail($data['payroll_sheet_id']);
            $this->assertSheetStage($sheet, 'draft', '工资表已提交，不能增加员工');
            $duplicate = PayrollItem::where('user_id', $data['user_id'])
                ->whereHas('sheet', fn ($q) => $q->where('period_month', $sheet->period_month->toDateString()))
                ->exists();
            abort_if($duplicate, 422, '该员工本月工资已添加，不能重复添加');
            $user = User::with('department')->findOrFail($data['user_id']);
            $item = PayrollItem::create(array_merge([
                'payroll_sheet_id' => $sheet->id,
                'user_id' => $user->id,
                'status' => 'pending',
                'manager_status' => 'pending',
            ], $this->payroll->snapshotUser($user), collect($data)->except(['payroll_sheet_id', 'user_id'])->all()));
            $this->payroll->recalculateItem($item);
            $this->payroll->syncSheetTotals($sheet->id);

            return $item->fresh()->load($this->itemRelations());
        });
    }

    public function updateItem(Request $request)
    {
        $data = $request->validate(array_merge([
            'id' => ['required', 'exists:payroll_items,id'],
            'calculation_detail' => ['nullable', 'array'],
        ], $this->amountRules(PayrollService::HR_FIELDS, true)));

        return DB::transaction(function () use ($data) {
            $item = PayrollItem::lockForUpdate()->findOrFail($data['id']);
            $this->assertItemMutable($item);
            $this->assertSheetStage($item->sheet, 'draft', '工资已交给下一角色，当前不能修改');
            $item->update(collect($data)->except('id')->all());
            $this->payroll->recalculateItem($item);
            $this->payroll->syncSheetTotals($item->payroll_sheet_id);

            return $item->fresh()->load($this->itemRelations());
        });
    }

    public function deleteItem(Request $request)
    {
        $request->validate(['id' => ['required', 'exists:payroll_items,id']]);

        return DB::transaction(function () use ($request) {
            $item = PayrollItem::lockForUpdate()->findOrFail($request->integer('id'));
            $this->assertItemMutable($item);
            $this->assertSheetStage($item->sheet, 'draft', '工资已交给下一角色，当前不能删除');
            $sheetId = $item->payroll_sheet_id;
            $item->delete();
            $this->payroll->syncSheetTotals($sheetId);

            return ['deleted' => true];
        });
    }

    public function submitManagerReview(Request $request)
    {
        $request->validate(['id' => ['required', 'exists:payroll_sheets,id'], 'comment' => ['nullable', 'string', 'max:1000']]);

        return DB::transaction(function () use ($request) {
            $sheet = PayrollSheet::lockForUpdate()->findOrFail($request->integer('id'));
            $this->assertSheetStage($sheet, 'draft', '当前工资表不能提交上级确认');
            abort_unless($sheet->items()->exists(), 422, '工资表没有员工明细');
            abort_if($sheet->items()->whereIn('status', ['confirmed', 'paid'])->exists(), 422, '存在员工已确认明细，不能重新提交');
            $sheet->items()->update([
                'manager_status' => 'pending', 'manager_confirmed_by' => null,
                'manager_confirmed_at' => null, 'manager_comment' => null,
            ]);
            $sheet->update([
                'status' => 'manager_review', 'submitted_by' => Auth::id(), 'submitted_at' => now(),
                'rejection_reason' => null,
            ]);
            $this->payroll->createApprovalCycle($sheet->fresh());
            $this->payroll->logApproval($sheet, 1, '人事提交工资', 'approved', $request->input('comment'));

            return $sheet->fresh()->load('items');
        });
    }

    public function managerReview(Request $request)
    {
        $data = $request->validate(array_merge([
            'id' => ['required', 'exists:payroll_items,id'],
            'action' => ['required', Rule::in(['approved', 'rejected'])],
            'comment' => ['nullable', 'required_if:action,rejected', 'string', 'max:1000'],
        ], $this->amountRules(PayrollService::PERFORMANCE_FIELDS, true)));

        return DB::transaction(function () use ($data) {
            $item = PayrollItem::lockForUpdate()->findOrFail($data['id']);
            $sheet = PayrollSheet::lockForUpdate()->findOrFail($item->payroll_sheet_id);
            $this->assertSheetStage($sheet, 'manager_review', '当前不在上级确认阶段');
            $this->assertItemMutable($item);
            abort_unless($this->payroll->canManageUser($item->user_id), 403, '只能确认本人管理范围内的下属工资');

            if ($data['action'] === 'rejected') {
                $item->update(['manager_status' => 'rejected', 'manager_confirmed_by' => Auth::id(), 'manager_confirmed_at' => now(), 'manager_comment' => $data['comment']]);
                $sheet->update(['status' => 'draft', 'rejection_reason' => $data['comment']]);
                $this->payroll->logApproval($sheet, 2, '上级确认业绩', 'rejected', $data['comment']);

                return $item->fresh()->load($this->itemRelations());
            }

            $values = collect($data)->only(PayrollService::PERFORMANCE_FIELDS)->all();
            $values += ['manager_status' => 'approved', 'manager_confirmed_by' => Auth::id(), 'manager_confirmed_at' => now(), 'manager_comment' => $data['comment'] ?? null];
            $item->update($values);
            $this->appendCalculationDetail($item, 'manager_review', $values);
            $this->payroll->recalculateItem($item);
            $this->payroll->syncSheetTotals($sheet->id);
            $this->payroll->logApproval($sheet, 2, '上级确认业绩-'.$item->employee_name, 'approved', $data['comment'] ?? null);

            return $item->fresh()->load($this->itemRelations());
        });
    }

    public function submitFinanceReview(Request $request)
    {
        $request->validate(['id' => ['required', 'exists:payroll_sheets,id']]);

        return DB::transaction(function () use ($request) {
            $sheet = PayrollSheet::lockForUpdate()->findOrFail($request->integer('id'));
            $this->assertSheetStage($sheet, 'manager_review', '当前工资表不能提交财务复核');
            abort_if($sheet->items()->where('manager_status', '!=', 'approved')->exists(), 422, '仍有员工工资未完成上级确认');
            $sheet->update(['status' => 'finance_review', 'rejection_reason' => null]);

            return $sheet->fresh();
        });
    }

    public function financeReview(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'exists:payroll_sheets,id'],
            'action' => ['required', Rule::in(['approved', 'rejected'])],
            'comment' => ['nullable', 'required_if:action,rejected', 'string', 'max:1000'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'exists:payroll_items,id'],
            ...$this->nestedAmountRules('items.*.', PayrollService::FINANCE_FIELDS),
        ]);

        return DB::transaction(function () use ($data) {
            $sheet = PayrollSheet::lockForUpdate()->findOrFail($data['id']);
            $this->assertSheetStage($sheet, 'finance_review', '当前不在财务复核阶段');
            if ($data['action'] === 'rejected') {
                $sheet->update(['status' => 'draft', 'finance_reviewed_by' => Auth::id(), 'finance_reviewed_at' => now(), 'finance_comment' => $data['comment'], 'rejection_reason' => $data['comment']]);
                $this->payroll->logApproval($sheet, 3, '财务复核', 'rejected', $data['comment']);

                return $sheet->fresh();
            }

            foreach ($data['items'] ?? [] as $itemData) {
                $item = PayrollItem::lockForUpdate()->findOrFail($itemData['id']);
                abort_unless($item->payroll_sheet_id === $sheet->id, 422, '工资明细不属于当前工资表');
                $this->assertItemMutable($item);
                $values = collect($itemData)->only(PayrollService::FINANCE_FIELDS)->all();
                $item->update($values);
                $this->appendCalculationDetail($item, 'finance_review', $values);
                $this->payroll->recalculateItem($item);
            }
            $this->payroll->syncSheetTotals($sheet->id);
            $sheet->update(['status' => 'admin_review', 'finance_reviewed_by' => Auth::id(), 'finance_reviewed_at' => now(), 'finance_comment' => $data['comment'] ?? null, 'rejection_reason' => null]);
            $this->payroll->logApproval($sheet, 3, '财务复核', 'approved', $data['comment'] ?? null);

            return $sheet->fresh()->load('items');
        });
    }

    public function adminReview(Request $request)
    {
        abort_unless($this->payroll->isAdmin(), 403, '仅超级管理员可以终审工资');
        $data = $request->validate([
            'id' => ['required', 'exists:payroll_sheets,id'],
            'action' => ['required', Rule::in(['approved', 'rejected'])],
            'comment' => ['nullable', 'required_if:action,rejected', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($data) {
            $sheet = PayrollSheet::lockForUpdate()->findOrFail($data['id']);
            $this->assertSheetStage($sheet, 'admin_review', '当前不在超级管理员终审阶段');
            if ($data['action'] === 'rejected') {
                $sheet->update(['status' => 'draft', 'admin_approved_by' => Auth::id(), 'admin_approved_at' => now(), 'admin_comment' => $data['comment'], 'rejection_reason' => $data['comment']]);
                $this->payroll->logApproval($sheet, 4, '超级管理员终审', 'rejected', $data['comment']);

                return $sheet->fresh();
            }

            $sheet->items()->whereNotIn('status', ['confirmed', 'paid'])->update(['status' => 'pending', 'appeal_content' => null, 'appeal_result' => null]);
            $sheet->update([
                'status' => 'employee_confirmation', 'admin_approved_by' => Auth::id(), 'admin_approved_at' => now(),
                'admin_comment' => $data['comment'] ?? null, 'locked_by' => Auth::id(), 'locked_at' => now(), 'rejection_reason' => null,
            ]);
            $this->payroll->logApproval($sheet, 4, '超级管理员终审', 'approved', $data['comment'] ?? null);
            $this->payroll->finishApproval($sheet);

            return $sheet->fresh()->load('items');
        });
    }

    public function adminAdjust(Request $request)
    {
        abort_unless($this->payroll->isAdmin(), 403, '仅超级管理员可以执行管理员调整');
        $data = $request->validate(array_merge([
            'id' => ['required', 'exists:payroll_items,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ], $this->amountRules(PayrollService::ALL_AMOUNT_FIELDS, true)));

        return DB::transaction(function () use ($data) {
            $item = PayrollItem::lockForUpdate()->findOrFail($data['id']);
            $this->assertItemMutable($item);
            $sheet = PayrollSheet::lockForUpdate()->findOrFail($item->payroll_sheet_id);
            abort_if(in_array($sheet->status, ['completed', 'paid'], true), 422, '员工确认完成后不能修改工资');
            $values = collect($data)->only(PayrollService::ALL_AMOUNT_FIELDS)->all();
            $item->update(array_merge($values, [
                'manager_status' => 'pending', 'manager_confirmed_by' => null, 'manager_confirmed_at' => null,
                'manager_comment' => null, 'status' => 'pending', 'employee_confirmed_by' => null, 'employee_confirmed_at' => null,
            ]));
            $this->appendCalculationDetail($item, 'admin_adjust', array_merge($values, ['reason' => $data['reason']]));
            $this->payroll->recalculateItem($item);
            $this->payroll->syncSheetTotals($sheet->id);
            $sheet->update([
                'status' => 'manager_review', 'locked_by' => null, 'locked_at' => null,
                'admin_approved_by' => null, 'admin_approved_at' => null, 'rejection_reason' => '超级管理员调整：'.$data['reason'],
            ]);
            $this->payroll->createApprovalCycle($sheet->fresh());
            $this->payroll->logApproval($sheet, 1, '超级管理员调整并重新送审', 'returned', $data['reason']);

            return $item->fresh()->load($this->itemRelations());
        });
    }

    public function employeeAction(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'exists:payroll_items,id'],
            'action' => ['required', Rule::in(['confirmed', 'appealed'])],
            'appeal_content' => ['nullable', 'required_if:action,appealed', 'string', 'max:2000'],
        ]);

        return DB::transaction(function () use ($data) {
            $item = PayrollItem::lockForUpdate()->findOrFail($data['id']);
            abort_unless($item->user_id === Auth::id(), 403, '只能确认或申诉本人工资');
            $sheet = PayrollSheet::lockForUpdate()->findOrFail($item->payroll_sheet_id);
            $this->assertSheetStage($sheet, 'employee_confirmation', '当前工资尚未进入员工确认阶段');
            abort_if(in_array($item->status, ['confirmed', 'paid'], true), 422, '该工资明细已经确认，不能重复操作或修改');

            if ($data['action'] === 'confirmed') {
                $item->update(['status' => 'confirmed', 'employee_confirmed_by' => Auth::id(), 'employee_confirmed_at' => now(), 'appeal_content' => null]);
            } else {
                $item->update(['status' => 'appealing', 'appeal_content' => $data['appeal_content'], 'appeal_result' => null]);
            }
            $this->completeSheetWhenReady($sheet);

            return $item->fresh()->load($this->itemRelations());
        });
    }

    public function resolveAppeal(Request $request)
    {
        $data = $request->validate(array_merge([
            'id' => ['required', 'exists:payroll_items,id'],
            'action' => ['required', Rule::in(['accepted', 'rejected'])],
            'result' => ['required', 'string', 'max:2000'],
            'return_stage' => ['nullable', 'required_if:action,accepted', Rule::in(['manager_review', 'finance_review'])],
        ], $this->amountRules(PayrollService::HR_FIELDS, true)));

        return DB::transaction(function () use ($data) {
            $item = PayrollItem::lockForUpdate()->findOrFail($data['id']);
            abort_unless($item->status === 'appealing', 422, '该工资明细不在申诉处理中');
            $sheet = PayrollSheet::lockForUpdate()->findOrFail($item->payroll_sheet_id);
            $this->assertSheetStage($sheet, 'employee_confirmation', '当前工资表不能处理申诉');
            $item->update(['appeal_result' => $data['result'], 'appeal_handled_by' => Auth::id(), 'appeal_handled_at' => now()]);

            if ($data['action'] === 'rejected') {
                $item->update(['status' => 'confirmed']);
                $this->completeSheetWhenReady($sheet);

                return $item->fresh()->load($this->itemRelations());
            }

            $values = collect($data)->only(PayrollService::HR_FIELDS)->all();
            $values += ['status' => 'pending', 'employee_confirmed_by' => null, 'employee_confirmed_at' => null];
            if ($data['return_stage'] === 'manager_review') {
                $values += ['manager_status' => 'pending', 'manager_confirmed_by' => null, 'manager_confirmed_at' => null, 'manager_comment' => null];
            }
            $item->update($values);
            $this->appendCalculationDetail($item, 'appeal_accepted', ['result' => $data['result'], 'return_stage' => $data['return_stage']]);
            $this->payroll->recalculateItem($item);
            $this->payroll->syncSheetTotals($sheet->id);
            $sheet->update([
                'status' => $data['return_stage'], 'locked_by' => null, 'locked_at' => null,
                'admin_approved_by' => null, 'admin_approved_at' => null, 'rejection_reason' => '员工申诉成立：'.$data['result'],
            ]);
            $this->payroll->createApprovalCycle($sheet->fresh());
            $this->payroll->logApproval($sheet, 1, '员工申诉成立并退回', 'returned', $data['result']);

            return $item->fresh()->load($this->itemRelations());
        });
    }

    public function markPaid(Request $request)
    {
        $request->validate(['id' => ['required', 'exists:payroll_sheets,id'], 'comment' => ['nullable', 'string', 'max:1000']]);

        return DB::transaction(function () use ($request) {
            $sheet = PayrollSheet::lockForUpdate()->findOrFail($request->integer('id'));
            $this->assertSheetStage($sheet, 'completed', '仅全部员工确认完成的工资表可以标记发放');
            abort_if($sheet->items()->where('status', '!=', 'confirmed')->exists(), 422, '仍有工资未确认或申诉未处理');
            $sheet->items()->update(['status' => 'paid']);
            $sheet->update(['status' => 'paid', 'paid_by' => Auth::id(), 'paid_at' => now()]);

            return $sheet->fresh()->load('items');
        });
    }

    private function scopedItem(int $id): PayrollItem
    {
        return $this->payroll->applyItemScope(PayrollItem::query())->findOrFail($id);
    }

    private function assertSheetStage(PayrollSheet $sheet, string $stage, string $message): void
    {
        abort_unless($sheet->status === $stage, 422, $message);
    }

    private function assertItemMutable(PayrollItem $item): void
    {
        abort_if(in_array($item->status, ['confirmed', 'paid'], true), 422, '员工确认后该工资明细永久冻结，不能修改');
    }

    private function amountRules(array $fields, bool $sometimes = false): array
    {
        $rules = [];
        foreach ($fields as $field) {
            $rules[$field] = [$sometimes ? 'sometimes' : 'nullable', 'numeric', 'min:0'];
        }

        return $rules;
    }

    private function nestedAmountRules(string $prefix, array $fields): array
    {
        $rules = [];
        foreach ($fields as $field) {
            $rules[$prefix.$field] = ['sometimes', 'numeric', 'min:0'];
        }

        return $rules;
    }

    private function appendCalculationDetail(PayrollItem $item, string $key, array $values): void
    {
        $detail = $item->calculation_detail ?: [];
        $detail[$key] = ['values' => $values, 'operated_by' => Auth::id(), 'operated_at' => now()->toDateTimeString()];
        $item->update(['calculation_detail' => $detail]);
    }

    private function completeSheetWhenReady(PayrollSheet $sheet): void
    {
        if (! $sheet->items()->whereIn('status', ['pending', 'appealing'])->exists()) {
            $sheet->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }

    private function itemRelations(): array
    {
        return ['sheet:id,sheet_no,period_month,status,formula_version', 'user:id,name,department_id,position_name', 'managerConfirmedBy:id,name', 'employeeConfirmedBy:id,name', 'appealHandledBy:id,name'];
    }

    private function sheetActions(PayrollSheet $sheet): array
    {
        $actions = [];
        if ($sheet->status === 'draft' && $this->allowed('hr.payroll.hr.manage')) {
            $actions = ['update', 'delete', 'submit_manager_review'];
        }
        if ($sheet->status === 'manager_review' && $this->allowed('hr.payroll.hr.manage')
            && ! $sheet->items()->where('manager_status', '!=', 'approved')->exists()) {
            $actions[] = 'submit_finance_review';
        }
        if ($sheet->status === 'finance_review' && $this->allowed('hr.payroll.finance.review')) {
            $actions[] = 'finance_review';
        }
        if ($sheet->status === 'admin_review' && $this->payroll->isAdmin()) {
            $actions[] = 'admin_review';
        }
        if ($sheet->status === 'completed' && $this->allowed('hr.payroll.pay')) {
            $actions[] = 'mark_paid';
        }

        return array_values(array_unique($actions));
    }

    private function itemActions(PayrollItem $item): array
    {
        $actions = [];
        $sheetStatus = optional($item->sheet)->status;
        if ($sheetStatus === 'draft' && $this->allowed('hr.payroll.hr.manage') && ! in_array($item->status, ['confirmed', 'paid'], true)) {
            $actions = ['update', 'delete'];
        }
        if ($sheetStatus === 'manager_review' && $item->manager_status === 'pending'
            && $this->allowed('hr.payroll.manager.confirm') && $this->payroll->canManageUser($item->user_id)) {
            $actions[] = 'manager_review';
        }
        if ($sheetStatus === 'employee_confirmation' && $item->user_id === Auth::id() && $item->status === 'pending') {
            $actions[] = 'employee_confirm';
            $actions[] = 'employee_appeal';
        }
        if ($sheetStatus === 'employee_confirmation' && $item->status === 'appealing' && $this->allowed('hr.payroll.appeal.resolve')) {
            $actions[] = 'appeal_resolve';
        }
        if ($this->payroll->isAdmin() && ! in_array($item->status, ['confirmed', 'paid'], true)
            && ! in_array($sheetStatus, ['completed', 'paid'], true)) {
            $actions[] = 'admin_adjust';
        }

        return array_values(array_unique($actions));
    }

    private function allowed(string $permission): bool
    {
        return $this->payroll->isAdmin() || Auth::user()->can($permission);
    }
}
