<?php

namespace App\Http\Controllers\Admin\Business;

use App\Http\Controllers\Controller;
use App\Http\Model\Order;
use App\Http\Model\OrderMember;
use App\Http\Model\OrderStageLog;
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
                    ->orWhere('product_name', 'like', "%{$keyword}%")
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
        $data = $this->validateOrder($request);
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
        $data = $this->validateOrder($request, $order->id, true);
        $this->validateContactCustomer(array_merge($order->only(['customer_id', 'contact_id']), $data));
        $assignmentChanges = collect($data)->only([
            'sales_user_id', 'sales_manager_id', 'technical_director_id', 'optimizer_id', 'owner_user_id',
        ])->all();
        if ($request->has('members')) {
            $assignmentChanges['members'] = $request->input('members', []);
        }
        $this->access->assertAssignableUsers($assignmentChanges);

        return DB::transaction(function () use ($request, $order, $data) {
            $order->update($this->normalizeAmounts(array_merge($order->only(['contract_amount', 'discount_amount', 'paid_amount']), $data)));
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
            'sales_manager_id' => ['nullable', 'exists:users,id'],
            'technical_director_id' => ['nullable', 'exists:users,id'],
            'optimizer_id' => ['nullable', 'exists:users,id'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
            'members' => ['sometimes', 'array'],
            'members.*.user_id' => ['required', 'exists:users,id'],
            'members.*.member_role' => ['required', Rule::in(['sales', 'sales_manager', 'technical_director', 'optimizer'])],
            'members.*.commission_ratio' => ['nullable', 'numeric', 'between:0,1'],
        ]);
        $order = Order::findOrFail($request->integer('id'));
        $this->access->authorizeView($order);
        abort_unless($this->access->canAssign($order), 403, '当前角色或订单阶段不允许分配成员');
        $this->access->assertAssignableUsers($data);
        unset($data['id'], $data['members']);

        return DB::transaction(function () use ($request, $order, $data) {
            $order->update($data);
            if ($request->has('members')) {
                $this->syncMembers($order, $request->input('members'));
            }

            return $this->withActions($order->fresh()->load(['members.user:id,name', 'technicalDirector:id,name', 'optimizer:id,name', 'owner:id,name']));
        });
    }

    private function validateOrder(Request $request, ?int $id = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $requiredFiles = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'order_no' => ['sometimes', 'nullable', 'max:64', Rule::unique('order', 'order_no')->ignore($id)],
            'customer_id' => [$required, 'exists:crm_customers,id'],
            'contact_id' => ['nullable', 'exists:crm_contacts,id'],
            'product_type' => [$required, Rule::in(['trial', 'annual', 'other'])],
            'product_name' => [$required, 'string', 'max:255'],
            'contract_no' => ['nullable', 'string', 'max:64', Rule::unique('order', 'contract_no')->ignore($id)],
            'contract_amount' => [$required, 'numeric', 'min:0'],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'payment_terms' => [$required, 'string', 'max:1000'],
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
            'service_objective' => ['nullable', 'string'],
            'success_criteria' => ['nullable', 'string'],
            'baseline_data' => ['nullable', 'array'],
            'required_materials' => ['nullable', 'array'],
            'materials_due_date' => ['nullable', 'date'],
            'kickoff_meeting_at' => ['nullable', 'date'],
            'kickoff_attendees' => ['nullable', 'array'],
            'sales_commitment' => ['nullable', 'string'],
            'ranking_commitment' => ['sometimes', 'boolean'],
            'acquisition_commitment' => ['sometimes', 'boolean'],
            'qualification_status' => ['sometimes', Rule::in(['pending', 'verified', 'missing'])],
            'case_authorized' => ['sometimes', 'boolean'],
            'logo_authorized' => ['sometimes', 'boolean'],
            'portrait_authorized' => ['sometimes', 'boolean'],
            'refund_terms' => ['nullable', 'string'],
            'special_delivery_terms' => ['nullable', 'string'],
            'complaint_history' => ['nullable', 'string'],
            'pending_verification' => ['nullable', 'string'],
            'risk_summary' => ['nullable', 'string'],
            'members' => ['sometimes', 'array'],
            'contract_files' => [$requiredFiles, 'array', 'min:1'],
            'contract_files.*' => ['url', 'max:1000'],
            'payment_voucher_files' => [$requiredFiles, 'array', 'min:1'],
            'payment_voucher_files.*' => ['url', 'max:1000'],
            'license_files' => [$requiredFiles, 'array', 'min:1'],
            'license_files.*' => ['url', 'max:1000'],
            'authorization_files' => [$requiredFiles, 'array', 'min:1'],
            'authorization_files.*' => ['url', 'max:1000'],
            'sales_handover_files' => [$requiredFiles, 'array', 'min:1'],
            'sales_handover_files.*' => ['url', 'max:1000'],
            'approval_files' => ['sometimes', 'array'],
            'approval_files.*' => ['url', 'max:1000'],
        ]);
    }

    private function normalizeAmounts(array $data): array
    {
        if (array_key_exists('contract_amount', $data) || array_key_exists('discount_amount', $data)) {
            $contract = (float) ($data['contract_amount'] ?? 0);
            $discount = (float) ($data['discount_amount'] ?? 0);
            abort_if($discount > $contract, 422, '折扣金额不能大于合同金额');
            $data['payable_amount'] = round($contract - $discount, 2);
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
            'finance_confirm' => ['sales_review', 'tech_assign', 'cancelled'], 'tech_assign' => ['finance_confirm', 'service', 'cancelled'],
            'service' => ['renewal', 'completed', 'cancelled'], 'renewal' => ['service', 'completed', 'cancelled'],
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
            'service' => ['technical_director'],
            'renewal', 'completed' => ['technical_director', 'optimizer'],
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
        if ($order->current_stage === 'tech_assign' && ($this->access->isAdmin() || in_array('technical_director', $roles, true))) {
            $actions[] = 'start_service';
        }
        if (in_array($order->current_stage, ['service', 'renewal'], true)
            && ($this->access->isAdmin() || array_intersect($roles, ['technical_director', 'optimizer']))) {
            $actions[] = 'complete';
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
