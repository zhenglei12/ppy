<?php

namespace App\Http\Controllers\Admin\Business;

use App\Http\Controllers\Controller;
use App\Http\Model\Order;
use App\Http\Model\OrderMember;
use App\Http\Model\OrderStageLog;
use App\Http\Model\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    private const STAGES = ['draft', 'sales_review', 'finance_confirm', 'tech_assign', 'service', 'renewal', 'completed', 'cancelled'];

    public function index(Request $request)
    {
        $query = Order::query()->with(['customer:id,customer_no,legal_name,brand_name', 'contact:id,contact_name,mobile', 'salesUser:id,name', 'owner:id,name']);
        $this->applyDataScope($query);

        $query->when($request->filled('keyword'), function ($query) use ($request) {
            $keyword = $request->input('keyword');
            $query->where(function ($query) use ($keyword) {
                $query->where('order_no', 'like', "%{$keyword}%")
                    ->orWhere('product_name', 'like', "%{$keyword}%")
                    ->orWhereHas('customer', fn ($q) => $q->where('legal_name', 'like', "%{$keyword}%")->orWhere('brand_name', 'like', "%{$keyword}%"));
            });
        });
        foreach (['customer_id', 'sales_user_id', 'owner_user_id'] as $field) {
            $query->when($request->filled($field), fn ($q) => $q->where($field, $request->integer($field)));
        }
        foreach (['current_stage', 'business_status', 'health_status', 'product_type'] as $field) {
            $query->when($request->filled($field), fn ($q) => $q->where($field, $request->input($field)));
        }
        $query->when($request->filled('next_action_start'), fn ($q) => $q->where('next_action_at', '>=', $request->input('next_action_start')))
            ->when($request->filled('next_action_end'), fn ($q) => $q->where('next_action_at', '<=', $request->input('next_action_end')))
            ->when($request->filled('created_start'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('created_start')))
            ->when($request->filled('created_end'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('created_end')));

        return $query->latest('id')->paginate($request->integer('pageSize', 20));
    }

    public function show(Request $request)
    {
        $order = Order::with([
            'customer.contacts', 'contact', 'salesUser:id,name,employee_no', 'salesManager:id,name,employee_no',
            'technicalDirector:id,name,employee_no', 'optimizer:id,name,employee_no', 'assistant:id,name,employee_no',
            'owner:id,name,employee_no', 'members', 'stageLogs' => fn ($q) => $q->latest('operated_at'),
            'contracts', 'paymentPlans', 'payments', 'deliveryProject.milestones',
        ])->findOrFail($request->integer('id'));
        $this->authorizeOrder($order);

        return $order;
    }

    public function store(Request $request)
    {
        $data = $this->validateOrder($request);
        $this->validateContactCustomer($data);

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

            return $order->load(['customer', 'contact', 'members', 'owner:id,name']);
        });
    }

    public function update(Request $request)
    {
        $order = Order::findOrFail($request->integer('id'));
        $this->authorizeOrder($order);
        abort_unless(in_array($order->current_stage, ['draft', 'sales_review'], true), 422, '当前阶段不允许直接修改订单');
        $data = $this->validateOrder($request, $order->id, true);
        $this->validateContactCustomer(array_merge($order->only(['customer_id', 'contact_id']), $data));

        return DB::transaction(function () use ($request, $order, $data) {
            $order->update($this->normalizeAmounts(array_merge($order->only(['contract_amount', 'discount_amount', 'paid_amount']), $data)));
            if ($request->has('members')) {
                $this->syncMembers($order, $request->input('members', []));
            }

            return $order->fresh()->load(['customer', 'contact', 'members', 'owner:id,name']);
        });
    }

    public function destroy(Request $request)
    {
        $order = Order::findOrFail($request->integer('id'));
        $this->authorizeOrder($order);
        abort_unless($order->current_stage === 'draft', 422, '仅草稿订单可以删除');
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
        $this->authorizeOrder($order);
        $this->assertTransition($order->current_stage, $request->input('to_stage'));
        $this->authorizeTransition($order->current_stage, $request->input('to_stage'));

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

            return $order->fresh()->load(['owner:id,name', 'stageLogs' => fn ($q) => $q->latest('operated_at')]);
        });
    }

    public function assign(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'exists:order,id'],
            'sales_manager_id' => ['nullable', 'exists:users,id'],
            'technical_director_id' => ['nullable', 'exists:users,id'],
            'optimizer_id' => ['nullable', 'exists:users,id'],
            'assistant_id' => ['nullable', 'exists:users,id'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
            'members' => ['sometimes', 'array'],
            'members.*.user_id' => ['required', 'exists:users,id'],
            'members.*.member_role' => ['required', Rule::in(['sales', 'sales_manager', 'technical_director', 'optimizer', 'assistant'])],
            'members.*.commission_ratio' => ['nullable', 'numeric', 'between:0,1'],
        ]);
        $order = Order::findOrFail($request->integer('id'));
        $this->authorizeOrder($order);
        unset($data['id'], $data['members']);

        return DB::transaction(function () use ($request, $order, $data) {
            $order->update($data);
            if ($request->has('members')) {
                $this->syncMembers($order, $request->input('members'));
            }

            return $order->fresh()->load(['members', 'technicalDirector:id,name', 'optimizer:id,name', 'assistant:id,name', 'owner:id,name']);
        });
    }

    private function validateOrder(Request $request, ?int $id = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'order_no' => ['sometimes', 'nullable', 'max:64', Rule::unique('order', 'order_no')->ignore($id)],
            'customer_id' => [$required, 'exists:crm_customers,id'],
            'contact_id' => ['nullable', 'exists:crm_contacts,id'],
            'product_type' => [$required, Rule::in(['trial', 'annual', 'other'])],
            'product_name' => [$required, 'string', 'max:255'],
            'contract_amount' => [$required, 'numeric', 'min:0'],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'payment_terms' => [$required, 'string', 'max:1000'],
            'sales_user_id' => ['sometimes', 'exists:users,id'],
            'sales_manager_id' => ['nullable', 'exists:users,id'],
            'technical_director_id' => ['nullable', 'exists:users,id'],
            'optimizer_id' => ['nullable', 'exists:users,id'],
            'assistant_id' => ['nullable', 'exists:users,id'],
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
            'sales_commitment' => ['nullable', 'string'],
            'risk_summary' => ['nullable', 'string'],
            'members' => ['sometimes', 'array'],
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

    private function applyDataScope($query): void
    {
        $user = Auth::user();
        $roles = $user->roles->pluck('alias')->all();
        if (array_intersect($roles, ['admin', 'sales_director', 'technical_director', 'finance'])) {
            return;
        }
        if (in_array('sales_manager', $roles, true)) {
            $ids = User::where('direct_manager_id', $user->id)->pluck('id')->push($user->id);
            $query->whereIn('sales_user_id', $ids);

            return;
        }
        $query->where(function ($q) use ($user) {
            $q->whereIn('sales_user_id', [$user->id])->orWhere('owner_user_id', $user->id)
                ->orWhere('optimizer_id', $user->id)->orWhere('assistant_id', $user->id)
                ->orWhereHas('members', fn ($member) => $member->where('user_id', $user->id)->whereNull('left_at'));
        });
    }

    private function authorizeOrder(Order $order): void
    {
        $query = Order::whereKey($order->id);
        $this->applyDataScope($query);
        abort_unless($query->exists(), 403, '无权访问该订单');
    }

    private function number(string $prefix): string
    {
        return $prefix.date('YmdHis').Str::upper(Str::random(4));
    }
}
