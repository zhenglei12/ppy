<?php

namespace App\Http\Controllers\Admin\Business;

use App\Http\Controllers\Controller;
use App\Http\Model\Order;
use App\Http\Model\Customer;
use App\Http\Model\DeliveryMilestone;
use App\Http\Model\DeliveryProject;
use App\Http\Model\OrderMember;
use App\Http\Model\OrderStageLog;
use App\Http\Model\Refund;
use App\Http\Model\User;
use App\Http\Services\OrderAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    private const STAGES = ['draft', 'sales_review', 'finance_confirm', 'tech_assign', 'service', 'renewal', 'completed', 'cancelled'];

    public function __construct(private OrderAccessService $access)
    {
    }

    public function index(Request $request)
    {
        $query = Order::query()->with([
            'customer:id,customer_no,legal_name,brand_name', 'contact:id,contact_name,mobile',
            'salesUser:id,name,department_id', 'salesManager:id,name,department_id',
            'technicalDirector:id,name,department_id', 'optimizer:id,name,department_id',
            'owner:id,name,department_id',
        ]);
        $this->access->applyScope($query);

        $query->when($request->filled('keyword'), function ($query) use ($request) {
            $keyword = $request->input('keyword');
            $query->where(function ($query) use ($keyword) {
                $query->where('order_no', 'like', "%{$keyword}%")
                    ->orWhere('customer_legal_name', 'like', "%{$keyword}%")
                    ->orWhereHas('customer', fn ($q) => $q->where('legal_name', 'like', "%{$keyword}%")->orWhere('brand_name', 'like', "%{$keyword}%"));
            });
        });
        foreach (['customer_id', 'sales_user_id', 'sales_manager_id', 'technical_director_id', 'optimizer_id', 'owner_user_id'] as $field) {
            $query->when($request->filled($field), fn ($q) => $q->where($field, $request->integer($field)));
        }
        foreach (['current_stage', 'business_status', 'health_status', 'product_type'] as $field) {
            $query->when($request->filled($field), fn ($q) => $q->where($field, $request->input($field)));
        }
        $query->when($request->filled('next_action_start'), fn ($q) => $q->where('next_action_at', '>=', $request->input('next_action_start')))
            ->when($request->filled('next_action_end'), fn ($q) => $q->where('next_action_at', '<=', $request->input('next_action_end')))
            ->when($request->filled('created_start'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('created_start')))
            ->when($request->filled('created_end'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('created_end')));

        $result = $query->latest('id')->paginate($request->integer('pageSize', 20));
        $result->getCollection()->each(fn (Order $order) => $order->setAttribute('available_actions', $this->availableActions($order)));

        return $result;
    }

    public function show(Request $request)
    {
        $order = Order::with([
            'customer.contacts', 'contact', 'salesUser:id,name,employee_no', 'salesManager:id,name,employee_no',
            'technicalDirector:id,name,employee_no', 'optimizer:id,name,employee_no',
            'owner:id,name,employee_no', 'members.user:id,name,employee_no,department_id,position_name', 'stageLogs' => fn ($q) => $q->latest('operated_at'),
            'contracts', 'paymentPlans', 'payments', 'deliveryProject.milestones',
        ])->findOrFail($request->integer('id'));
        $this->access->authorizeView($order);
        $order->setAttribute('available_actions', $this->availableActions($order));

        return $order;
    }

    public function store(Request $request)
    {
        $this->resolveCustomerInput($request);
        $data = $this->validateOrder($request);
        unset($data['customer_name'], $data['contact_name']);
        $this->validateContactCustomer($data);
        $candidate = $data;
        $candidate['sales_user_id'] = $candidate['sales_user_id'] ?? Auth::id();
        $candidate['owner_user_id'] = $candidate['owner_user_id'] ?? $candidate['sales_user_id'];
        $candidate['members'] = $request->input('members', []);
        $this->access->assertAssignableUsers($candidate);

        return DB::transaction(function () use ($request, $data) {
            $data = $this->normalizeAmounts($data);
            $data['order_no'] = $data['order_no'] ?? $this->number('ORD');
            $data['sales_user_id'] = $data['sales_user_id'] ?? Auth::id();
            $data['owner_user_id'] = $data['owner_user_id'] ?? $data['sales_user_id'];
            $data['created_by'] = Auth::id();
            $data['current_stage'] = $data['current_stage'] ?? 'draft';
            $data['business_status'] = $data['business_status'] ?? 'pending';
            $data['health_status'] = $data['health_status'] ?? 'green';
            $data['paid_amount'] = 0;
            $data['receivable_amount'] = $data['payable_amount'];
            $order = Order::create($data);
            $this->syncMembers($order, $request->input('members', []));
            $this->logStage($order, null, $order->current_stage);

            return $this->withActions($order->load(['customer', 'contact', 'members.user:id,name', 'owner:id,name']));
        });
    }

    public function update(Request $request)
    {
        $order = Order::findOrFail($request->integer('id'));
        $this->access->authorizeView($order);
        abort_unless($this->access->canEdit($order), 403, '当前用户或订单阶段不允许修改');
        $this->resolveCustomerInput($request, $order);
        $data = $this->validateOrder($request, $order->id, true);
        unset($data['customer_name'], $data['contact_name']);
        $this->validateContactCustomer(array_merge($order->only(['customer_id', 'contact_id']), $data));
        $assignmentChanges = collect($data)->only([
            'sales_user_id', 'sales_manager_id', 'technical_director_id', 'optimizer_id', 'owner_user_id',
        ])->all();
        if ($request->has('members')) {
            $assignmentChanges['members'] = $request->input('members', []);
        }
        $this->access->assertAssignableUsers($assignmentChanges);

        return DB::transaction(function () use ($request, $order, $data) {
            $order->update($this->normalizeAmounts(array_merge($order->only(['contract_amount', 'paid_amount']), $data)));
            if ($request->has('members')) {
                $this->syncMembers($order, $request->input('members', []));
            }

            return $this->withActions($order->fresh()->load(['customer', 'contact', 'members.user:id,name', 'owner:id,name']));
        });
    }

    public function destroy(Request $request)
    {
        $order = Order::findOrFail($request->integer('id'));
        $this->access->authorizeView($order);
        abort_unless($this->access->canDelete($order), 403, '仅草稿订单的创建人或其管理上级可以删除');
        $order->delete();

        return ['deleted' => true];
    }

    public function transition(Request $request)
    {
        $request->validate([
            'id' => ['required', 'exists:order,id'],
            'to_stage' => ['required', Rule::in(self::STAGES)],
            'owner_user_id' => ['required', 'exists:users,id'],
            'next_action' => ['nullable', 'string', 'max:500'],
            'next_action_at' => ['nullable', 'date'],
            'evidence_note' => ['nullable', 'string'],
        ]);
        $order = Order::findOrFail($request->integer('id'));
        $this->access->authorizeView($order);
        $this->assertTransition($order->current_stage, $request->input('to_stage'));
        $this->authorizeTransition($order->current_stage, $request->input('to_stage'));
        if ($request->integer('owner_user_id') !== $order->owner_user_id) {
            $this->access->assertAssignableUsers(['owner_user_id' => $request->integer('owner_user_id')]);
        }

        return DB::transaction(function () use ($request, $order) {
            $from = $order->current_stage;
            $to = $request->input('to_stage');
            $values = [
                'current_stage' => $to,
                'owner_user_id' => $request->integer('owner_user_id'),
                'next_action' => $request->input('next_action'),
                'next_action_at' => $request->input('next_action_at'),
            ];
            if ($to === 'sales_review' && ! $order->submitted_at) {
                $values['submitted_at'] = now();
            }
            if ($to === 'service') {
                $values['business_status'] = 'active';
            }
            if ($to === 'completed') {
                $values['business_status'] = 'completed';
                $values['completed_at'] = now();
            }
            if ($to === 'cancelled') {
                $values['business_status'] = 'cancelled';
            }
            $order->update($values);
            $this->logStage($order, $from, $to, $request->input('evidence_note'));

            return $this->withActions($order->fresh()->load(['owner:id,name', 'stageLogs' => fn ($q) => $q->latest('operated_at')]));
        });
    }

    public function assign(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'exists:order,id'],
            'optimizer_id' => ['required', 'exists:users,id'],
        ]);
        abort_unless(
            User::query()->whereKey($data['optimizer_id'])->whereHas('roles', fn ($role) => $role->where('alias', 'optimizer'))->exists(),
            422,
            '所选员工不是优化师'
        );
        $order = Order::findOrFail($request->integer('id'));
        $this->access->authorizeView($order);
        abort_unless($this->access->canAssign($order), 403, '当前角色或订单阶段不允许分配成员');
        unset($data['id']);

        return DB::transaction(function () use ($order, $data) {
            $from = $order->current_stage;
            $technicalDirectorId = $order->technical_director_id;
            if (! $technicalDirectorId && in_array('technical_director', $this->access->roles(), true)) {
                $technicalDirectorId = Auth::id();
            }
            $order->update(array_merge($data, [
                'technical_director_id' => $technicalDirectorId,
                'current_stage' => 'service',
                'business_status' => 'active',
                'actual_start_date' => $order->actual_start_date ?: now()->toDateString(),
            ]));
            $this->syncDeliveryProject($order->fresh());
            if ($from !== 'service') {
                $this->logStage($order->fresh(), $from, 'service', '分配优化师并启动服务');
            }

            return $this->withActions($order->fresh()->load(['members.user:id,name', 'technicalDirector:id,name', 'optimizer:id,name', 'owner:id,name']));
        });
    }

    public function updateStatus(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'exists:order,id'],
            'status' => ['required', Rule::in(['renewal', 'completed', 'cancelled'])],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);
        $order = Order::findOrFail($data['id']);
        $this->access->authorizeView($order);
        abort_unless(in_array($order->current_stage, ['service', 'renewal', 'completed'], true), 422, '仅服务中、续费中或已完成的订单可以修改状态');
        abort_if($order->current_stage === $data['status'], 422, '订单已经是当前状态');

        return DB::transaction(function () use ($order, $data) {
            $from = $order->current_stage;
            $values = [
                'current_stage' => $data['status'],
                'business_status' => match ($data['status']) {
                    'completed' => 'completed',
                    'cancelled' => 'cancelled',
                    default => 'active',
                },
            ];
            if ($data['status'] === 'completed') {
                $values['completed_at'] = now();
            }
            $order->update($values);
            $this->syncDeliveryProject($order->fresh());
            $this->logStage($order->fresh(), $from, $data['status'], $data['remark'] ?? null);

            return $this->withActions($order->fresh()->load(['optimizer:id,name', 'owner:id,name']));
        });
    }

    private function syncDeliveryProject(Order $order): DeliveryProject
    {
        $project = DeliveryProject::withTrashed()->firstOrNew(['order_id' => $order->id]);
        $isNew = ! $project->exists;
        if ($project->trashed()) {
            $project->restore();
        }
        if ($isNew) {
            $project->project_no = 'PRJORD'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT);
            $project->created_by = Auth::id();
        }
        $project->technical_director_id = $order->technical_director_id;
        $project->optimizer_id = $order->optimizer_id;
        $project->planned_start_date = $order->expected_start_date;
        $project->actual_start_date = $order->actual_start_date;
        $project->planned_end_date = $order->expected_end_date;
        $project->status = match ($order->current_stage) {
            'completed' => 'completed',
            'cancelled' => 'terminated',
            default => 'active',
        };
        $project->save();

        if ($isNew) {
            $start = $project->planned_start_date ?: now();
            foreach ([['D1', '成功标准确认', 1], ['D3', '成功标准完成', 2], ['D7', '事实与基线', 3], ['D15', '方向确认', 4], ['D30', '价值复盘', 5], ['D60', '续费预警', 6], ['D90', '报价与到账', 7]] as [$code, $name, $sequence]) {
                DeliveryMilestone::create([
                    'project_id' => $project->id,
                    'milestone_code' => $code,
                    'milestone_name' => $name,
                    'sequence_no' => $sequence,
                    'planned_at' => $start->copy()->addDays((int) substr($code, 1)),
                ]);
            }
        }

        return $project;
    }

    public function refund(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'exists:order,id'],
            'refund_amount' => ['required', 'numeric', 'gt:0'],
            'refund_reason' => ['required', 'string', 'max:1000'],
            'refund_screenshot_files' => ['required', 'array', 'min:1'],
            'refund_screenshot_files.*' => ['url', 'max:1000'],
        ]);
        $order = Order::findOrFail($data['id']);
        $this->access->authorizeView($order);
        abort_if((float) $data['refund_amount'] > (float) $order->paid_amount, 422, '退款金额不能大于订单已收金额');
        abort_if(Refund::where('order_id', $order->id)->whereIn('status', ['pending', 'approved'])->exists(), 422, '该订单已有待处理退款申请');

        return DB::transaction(function () use ($order, $data) {
            $refund = Refund::create([
                'refund_no' => 'REF-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6)),
                'order_id' => $order->id,
                'refund_amount' => $data['refund_amount'],
                'refund_reason' => $data['refund_reason'],
                'status' => 'pending',
                'requested_by' => Auth::id(),
                'remark' => '订单售后申请，退款截图已保存至订单',
            ]);
            $order->update([
                'refund_amount' => $data['refund_amount'],
                'refund_reason' => $data['refund_reason'],
                'refund_status' => 'pending',
                'refund_screenshot_files' => $data['refund_screenshot_files'],
                'refund_applied_at' => now(),
            ]);

            return [
                'order' => $this->withActions($order->fresh()->load(['optimizer:id,name', 'owner:id,name'])),
                'refund' => $refund,
            ];
        });
    }

    public function optimizers()
    {
        return User::query()
            ->select(['id', 'name', 'employee_no', 'department_id', 'position_name'])
            ->whereHas('roles', fn ($role) => $role->where('alias', 'optimizer'))
            ->with('department:id,name')
            ->orderBy('id')
            ->get();
    }

    private function validateOrder(Request $request, ?int $id = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $paymentVoucherRule = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'order_no' => ['sometimes', 'nullable', 'max:64', Rule::unique('order', 'order_no')->ignore($id)],
            'customer_id' => [$required, 'exists:crm_customers,id'],
            'contact_id' => ['nullable', 'exists:crm_contacts,id'],
            'customer_name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'contact_name' => [$partial ? 'sometimes' : 'required', 'string', 'max:64'],
            'customer_legal_name' => [$required, 'string', 'max:255'],
            'customer_credit_code' => ['nullable', 'string', 'max:32'],
            'customer_mobile' => ['nullable', 'string', 'max:32'],
            'customer_wechat' => ['nullable', 'string', 'max:64'],
            'customer_industry' => ['nullable', 'string', 'max:128'],
            'product_type' => [$required, Rule::in(['trial', 'annual', 'other'])],
            'contract_no' => ['nullable', 'string', 'max:64', Rule::unique('order', 'contract_no')->ignore($id)],
            'contract_amount' => [$required, 'numeric', 'min:0'],
            'payment_method' => ['nullable', Rule::in(['bank', 'wechat', 'alipay', 'cash', 'other'])],
            'payment_subject' => ['nullable', 'string', 'max:255'],
            'payment_due_date' => ['nullable', 'date'],
            'company_account' => ['nullable', 'string', 'max:255'],
            'invoice_required' => ['sometimes', 'boolean'],
            'invoice_type' => ['nullable', Rule::in(['normal', 'special', 'electronic'])],
            'invoice_title' => ['nullable', 'string', 'max:255'],
            'invoice_tax_no' => ['nullable', 'string', 'max:64'],
            'invoice_status' => ['sometimes', Rule::in(['not_applied', 'applied', 'issued'])],
            'sales_user_id' => ['sometimes', 'exists:users,id'],
            'sales_manager_id' => ['nullable', 'exists:users,id'],
            'technical_director_id' => ['nullable', 'exists:users,id'],
            'optimizer_id' => ['nullable', 'exists:users,id'],
            'owner_user_id' => ['sometimes', 'exists:users,id'],
            'health_status' => ['sometimes', Rule::in(['green', 'yellow', 'red'])],
            'next_action' => ['nullable', 'string', 'max:500'],
            'next_action_at' => ['nullable', 'date'],
            'expected_start_date' => ['nullable', 'date'],
            'actual_start_date' => ['nullable', 'date'],
            'service_cycle_days' => ['nullable', 'integer', 'min:1'],
            'expected_end_date' => ['nullable', 'date'],
            'customer_owner_name' => ['nullable', 'string', 'max:100'],
            'primary_business' => ['nullable', 'string', 'max:255'],
            'target_region' => ['nullable', 'string', 'max:255'],
            'kickoff_meeting_at' => ['nullable', 'date'],
            'sales_commitment' => ['nullable', 'string'],
            'ranking_commitment' => ['sometimes', 'boolean'],
            'acquisition_commitment' => ['sometimes', 'boolean'],
            'qualification_status' => ['sometimes', Rule::in(['pending', 'verified', 'missing'])],
            'case_authorized' => ['sometimes', 'boolean'],
            'logo_authorized' => ['sometimes', 'boolean'],
            'portrait_authorized' => ['sometimes', 'boolean'],
            'complaint_history' => ['nullable', 'string'],
            'members' => ['sometimes', 'array'],
            'contract_files' => ['sometimes', 'nullable', 'array'],
            'contract_files.*' => ['url', 'max:1000'],
            'payment_voucher_files' => [$paymentVoucherRule, 'array', 'min:1'],
            'payment_voucher_files.*' => ['url', 'max:1000'],
            'license_files' => ['sometimes', 'nullable', 'array'],
            'license_files.*' => ['url', 'max:1000'],
            'authorization_files' => ['sometimes', 'nullable', 'array'],
            'authorization_files.*' => ['url', 'max:1000'],
            'sales_handover_files' => ['sometimes', 'nullable', 'array'],
            'sales_handover_files.*' => ['url', 'max:1000'],
            'approval_files' => ['sometimes', 'array'],
            'approval_files.*' => ['url', 'max:1000'],
        ]);
    }

    private function normalizeAmounts(array $data): array
    {
        if (array_key_exists('contract_amount', $data)) {
            $contract = (float) ($data['contract_amount'] ?? 0);
            $data['payable_amount'] = round($contract, 2);
            $paid = (float) ($data['paid_amount'] ?? 0);
            abort_if($paid > $data['payable_amount'], 422, '已付金额不能大于应付金额');
            $data['receivable_amount'] = round($data['payable_amount'] - $paid, 2);
        }

        return $data;
    }

    private function validateContactCustomer(array $data): void
    {
        if (! empty($data['contact_id'])) {
            abort_unless(
                \App\Http\Model\Contact::whereKey($data['contact_id'])->where('customer_id', $data['customer_id'])->exists(),
                422,
                '联系人不属于所选客户'
            );
        }
    }

    /** 将前端直接输入的客户主体和联系人落到客户档案，订单仍保存标准外键。 */
    private function resolveCustomerInput(Request $request, ?Order $order = null): void
    {
        $customerName = trim((string) $request->input('customer_name', ''));
        $contactName = trim((string) $request->input('contact_name', ''));
        if ($customerName === '') {
            return;
        }

        $customer = Customer::where('legal_name', $customerName)->first();
        if (! $customer) {
            $customer = Customer::create([
                'customer_no' => $this->number('CUS'),
                'legal_name' => $customerName,
                'owner_user_id' => Auth::id(),
                'status' => 'lead',
            ]);
        }
        $request->merge(['customer_id' => $customer->id]);
        if (! $request->filled('customer_legal_name')) {
            $request->merge(['customer_legal_name' => $customerName]);
        }

        if ($contactName !== '') {
            $contact = $customer->contacts()->where('contact_name', $contactName)->first();
            if (! $contact) {
                $contact = $customer->contacts()->create(['contact_name' => $contactName, 'is_primary' => true]);
            }
            $request->merge(['contact_id' => $contact->id]);
        }
    }

    private function syncMembers(Order $order, array $members): void
    {
        $order->members()->whereNull('left_at')->update(['left_at' => now()]);
        foreach ($members as $member) {
            OrderMember::updateOrCreate(
                ['order_id' => $order->id, 'user_id' => $member['user_id'], 'member_role' => $member['member_role']],
                ['commission_ratio' => $member['commission_ratio'] ?? 0, 'joined_at' => $member['joined_at'] ?? now(), 'left_at' => null]
            );
        }
    }

    private function logStage(Order $order, ?string $from, string $to, ?string $evidence = null): void
    {
        OrderStageLog::create([
            'order_id' => $order->id, 'from_stage' => $from, 'to_stage' => $to,
            'owner_user_id' => $order->owner_user_id, 'next_action' => $order->next_action,
            'deadline_at' => $order->next_action_at, 'evidence_note' => $evidence,
            'operated_by' => Auth::id(), 'operated_at' => now(),
        ]);
    }

    private function assertTransition(string $from, string $to): void
    {
        $allowed = [
            'draft' => ['sales_review', 'cancelled'], 'sales_review' => ['draft', 'finance_confirm', 'cancelled'],
            'finance_confirm' => ['sales_review', 'tech_assign', 'cancelled'], 'tech_assign' => ['finance_confirm', 'cancelled'],
            'service' => [], 'renewal' => [],
            'completed' => [], 'cancelled' => [],
        ];
        abort_unless(in_array($to, $allowed[$from] ?? [], true), 422, "不允许从 {$from} 流转到 {$to}");
    }

    private function authorizeTransition(string $from, string $to): void
    {
        $roles = Auth::user()->roles->pluck('alias')->all();
        if (in_array('admin', $roles, true)) {
            return;
        }

        $allowedRoles = match ($to) {
            'sales_review' => ['sales', 'sales_manager', 'sales_director'],
            'draft', 'finance_confirm' => ['sales_manager', 'sales_director'],
            'tech_assign' => ['finance'],
            'cancelled' => ['sales_director', 'technical_director', 'finance'],
            default => [],
        };
        abort_unless(array_intersect($roles, $allowedRoles), 403, '当前角色无权执行该阶段流转');
    }

    private function availableActions(Order $order): array
    {
        $actions = ['view'];
        $roles = $this->access->roles();
        if ($this->access->canEdit($order)) {
            $actions[] = 'update';
        }
        if ($this->access->canDelete($order)) {
            $actions[] = 'delete';
        }
        if ($this->access->canAssign($order)) {
            $actions[] = 'assign';
        }
        if ($order->current_stage === 'draft' && ($this->access->isAdmin() || array_intersect($roles, ['sales', 'sales_manager', 'sales_director']))) {
            $actions[] = 'submit_sales_review';
        }
        if ($order->current_stage === 'sales_review' && ($this->access->isAdmin() || array_intersect($roles, ['sales_manager', 'sales_director']))) {
            $actions[] = 'approve_sales';
            $actions[] = 'return_draft';
        }
        if ($order->current_stage === 'finance_confirm' && ($this->access->isAdmin() || in_array('finance', $roles, true))) {
            $actions[] = 'confirm_finance';
        }
        if (in_array($order->current_stage, ['service', 'renewal', 'completed'], true)
            && $this->access->hasPermission('sales.order.status')) {
            $actions[] = 'change_status';
        }
        if ($this->access->hasPermission('sales.order.refund')) {
            $actions[] = 'refund';
        }

        return array_values(array_unique($actions));
    }

    private function withActions(Order $order): Order
    {
        $order->setAttribute('available_actions', $this->availableActions($order));

        return $order;
    }

    private function number(string $prefix): string
    {
        return $prefix.date('YmdHis').Str::upper(Str::random(4));
    }
}
