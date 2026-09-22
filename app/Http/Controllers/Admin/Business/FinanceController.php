<?php

namespace App\Http\Controllers\Admin\Business;

use App\Http\Controllers\Controller;
use App\Http\Model\Invoice;
use App\Http\Model\Order;
use App\Http\Model\Payment;
use App\Http\Model\PaymentPlan;
use App\Http\Model\Refund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    public function dashboard()
    {
        $orderIds = $this->visibleOrderIds();
        // 订单的财务确认到账金额存储在 order.paid_amount，收款记录表用于展示明细和待确认记录。
        $orders = Order::whereIn('id', $orderIds)->whereNotIn('business_status', ['cancelled']);
        $confirmedAmount = (clone $orders)->sum('paid_amount');
        $confirmedCount = (clone $orders)->where('paid_amount', '>', 0)->count();
        $receivableAmount = (clone $orders)->sum('receivable_amount');
        $pendingAmount = max(0, (float) $receivableAmount - (float) $confirmedAmount);
        $receivableCount = (clone $orders)->where('receivable_amount', '>', 0)->count();
        $pendingReviewOrders = (clone $orders)->where('current_stage', 'finance_confirm');
        $pendingReviewOrderCount = (clone $pendingReviewOrders)->count();
        $pendingReviewOrderAmount = (clone $pendingReviewOrders)->sum(DB::raw('GREATEST(payable_amount - paid_amount, 0)'));
        $pendingPayments = Payment::whereIn('order_id', $orderIds)->where('confirmation_status', 'pending');
        $paymentDistribution = Payment::whereIn('order_id', $orderIds)
            ->select('confirmation_status', DB::raw('count(*) as total'))
            ->groupBy('confirmation_status')
            ->pluck('total', 'confirmation_status')
            ->toArray();
        if ($confirmedCount > 0) {
            $paymentDistribution['confirmed'] = $confirmedCount;
        }

        $refundOrders = (clone $orders)->whereNotNull('refund_status')->whereNotIn('refund_status', ['rejected', 'cancelled']);

        return [
            'pending_review_orders' => (clone $orders)->where('current_stage', 'finance_confirm')
                ->select('id', 'order_no', 'customer_legal_name', 'customer_industry', 'payable_amount', 'paid_amount')
                ->latest('id')->limit(10)->get(),
            'pending_review_order_count' => $pendingReviewOrderCount,
            'pending_review_order_amount' => $pendingReviewOrderAmount,
            'confirmed_amount' => $confirmedAmount,
            'confirmed_count' => $confirmedCount,
            'pending_amount' => $pendingAmount,
            'pending_count' => $receivableCount,
            'pending_payment_amount' => (clone $pendingPayments)->sum('amount'),
            'pending_payment_count' => (clone $pendingPayments)->count(),
            'receivable_amount' => $receivableAmount,
            'overdue_plan_amount' => PaymentPlan::whereIn('order_id', $orderIds)->whereIn('status', ['pending', 'partial', 'overdue'])->whereDate('due_date', '<', today())->sum(DB::raw('planned_amount - paid_amount')),
            'overdue_plan_count' => PaymentPlan::whereIn('order_id', $orderIds)->whereIn('status', ['pending', 'partial', 'overdue'])->whereDate('due_date', '<', today())->count(),
            'invoice_pending_count' => Invoice::whereIn('order_id', $orderIds)->whereIn('status', ['requested', 'reviewing'])->count(),
            'refunded_amount' => (clone $refundOrders)->sum('refund_amount'),
            'refund_count' => (clone $refundOrders)->count(),
            'payment_status_distribution' => $paymentDistribution,
            'receivable_aging' => [
                '0_30_days' => PaymentPlan::whereIn('order_id', $orderIds)->whereIn('status', ['pending', 'partial', 'overdue'])->whereBetween('due_date', [today()->subDays(30), today()])->sum(DB::raw('planned_amount - paid_amount')),
                '31_60_days' => PaymentPlan::whereIn('order_id', $orderIds)->whereIn('status', ['pending', 'partial', 'overdue'])->whereBetween('due_date', [today()->subDays(60), today()->subDays(31)])->sum(DB::raw('planned_amount - paid_amount')),
                'over_60_days' => PaymentPlan::whereIn('order_id', $orderIds)->whereIn('status', ['pending', 'partial', 'overdue'])->where('due_date', '<', today()->subDays(60))->sum(DB::raw('planned_amount - paid_amount')),
            ],
        ];
    }

    public function plans(Request $request)
    {
        return PaymentPlan::with(['order:id,order_no,customer_id,customer_legal_name,payable_amount', 'order.customer:id,legal_name,brand_name'])->whereIn('order_id', $this->visibleOrderIds())
            ->when($request->filled('order_id'), fn($q) => $q->where('order_id', $request->integer('order_id')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->boolean('overdue'), fn($q) => $q->whereDate('due_date', '<', today())->whereIn('status', ['pending', 'partial', 'overdue']))
            ->orderBy('due_date')->paginate($request->integer('pageSize', 20));
    }

    public function savePlan(Request $request)
    {
        $id = $request->integer('id') ?: null;
        $data = $request->validate([
            'id' => ['nullable', 'exists:payment_plans,id'],
            'order_id' => ['required', 'exists:order,id'],
            'plan_no' => ['sometimes', 'nullable', 'max:64', Rule::unique('payment_plans', 'plan_no')->ignore($id)],
            'installment_no' => ['required', 'integer', 'min:1'],
            'planned_amount' => ['required', 'numeric', 'gt:0'],
            'due_date' => ['required', 'date'],
            'follower_user_id' => ['nullable', 'exists:users,id'],
            'next_follow_at' => ['nullable', 'date'],
            'remark' => ['nullable', 'string', 'max:500'],
        ]);
        $data['plan_no'] = $data['plan_no'] ?? $this->number('PLAN');
        unset($data['id']);
        $plan = $id ? PaymentPlan::findOrFail($id) : new PaymentPlan();
        $plan->fill($data);
        $plan->status = $this->planStatus((float) $plan->planned_amount, (float) ($plan->paid_amount ?? 0), $plan->due_date);
        $plan->save();

        return $plan->fresh()->load('order.customer');
    }

    public function payments(Request $request)
    {
        return Payment::with(['order:id,order_no,customer_id,customer_legal_name,payable_amount,paid_amount,receivable_amount', 'order.customer:id,legal_name,brand_name', 'plan'])->whereIn('order_id', $this->visibleOrderIds())
            ->when($request->filled('order_id'), fn($q) => $q->where('order_id', $request->integer('order_id')))
            ->when($request->filled('confirmation_status'), fn($q) => $q->where('confirmation_status', $request->input('confirmation_status')))
            ->when($request->filled('paid_start'), fn($q) => $q->where('paid_at', '>=', $request->input('paid_start')))
            ->when($request->filled('paid_end'), fn($q) => $q->where('paid_at', '<=', $request->input('paid_end')))
            ->latest('paid_at')->paginate($request->integer('pageSize', 20));
    }

    public function createPayment(Request $request)
    {
        $this->requireRole(['admin', 'finance', 'sales', 'sales_manager', 'sales_director']);
        $data = $request->validate([
            'order_id' => ['required', 'exists:order,id'],
            'payment_plan_id' => ['nullable', 'exists:payment_plans,id'],
            'payer_name' => ['required', 'string', 'max:255'],
            'company_account' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_at' => ['required', 'date'],
            'payment_method' => ['nullable', Rule::in(['bank', 'wechat', 'alipay', 'cash', 'other'])],
            'bank_serial_no' => ['nullable', 'string', 'max:128'],
            'voucher_url' => ['nullable', 'string', 'max:1000'],
        ]);
        if (! empty($data['payment_plan_id'])) {
            abort_unless(PaymentPlan::whereKey($data['payment_plan_id'])->where('order_id', $data['order_id'])->exists(), 422, '收款计划不属于该订单');
        }
        $data['payment_no'] = $this->number('PAY');
        $data['created_by'] = Auth::id();

        return Payment::create($data)->load('order.customer', 'plan');
    }

    public function confirmPayment(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'exists:payments,id'],
            'confirmation_status' => ['required', Rule::in(['confirmed', 'rejected', 'abnormal'])],
            'rejection_reason' => ['nullable', 'required_unless:confirmation_status,confirmed', 'string', 'max:500'],
        ]);

        return DB::transaction(function () use ($data) {
            $payment = Payment::lockForUpdate()->findOrFail($data['id']);
            $payment->update([
                'confirmation_status' => $data['confirmation_status'],
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
                'rejection_reason' => $data['rejection_reason'] ?? null,
            ]);
            $this->syncOrderFinance($payment->order_id);

            return $payment->fresh()->load('order.customer', 'plan');
        });
    }

    public function invoices(Request $request)
    {
        return Invoice::with('order.customer')->whereIn('order_id', $this->visibleOrderIds())->when($request->filled('order_id'), fn($q) => $q->where('order_id', $request->integer('order_id')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->latest('id')->paginate($request->integer('pageSize', 20));
    }

    public function saveInvoice(Request $request)
    {
        $id = $request->integer('id') ?: null;
        $data = $request->validate([
            'id' => ['nullable', 'exists:invoices,id'],
            'order_id' => ['required', 'exists:order,id'],
            'invoice_no' => ['nullable', 'max:64', Rule::unique('invoices', 'invoice_no')->ignore($id)],
            'invoice_title' => ['required', 'max:255'],
            'tax_no' => ['nullable', 'max:64'],
            'invoice_type' => ['required', Rule::in(['normal', 'special', 'electronic'])],
            'invoice_amount' => ['required', 'numeric', 'gt:0'],
            'status' => ['sometimes', Rule::in(['requested', 'reviewing', 'issued', 'voided', 'rejected'])],
            'issued_at' => ['nullable', 'date'],
            'invoice_file_url' => ['nullable', 'max:1000'],
            'remark' => ['nullable', 'max:500'],
        ]);
        $data['requested_by'] = $id ? Invoice::findOrFail($id)->requested_by : Auth::id();
        $data['requested_at'] = $id ? Invoice::findOrFail($id)->requested_at : now();
        if (($data['status'] ?? null) === 'issued') {
            $data['issued_by'] = Auth::id();
        }
        unset($data['id']);

        return Invoice::updateOrCreate(['id' => $id], $data)->load('order.customer');
    }

    public function refunds(Request $request)
    {
        return Refund::with(['order.customer', 'payment'])->whereIn('order_id', $this->visibleOrderIds())->when($request->filled('order_id'), fn($q) => $q->where('order_id', $request->integer('order_id')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->latest('id')->paginate($request->integer('pageSize', 20));
    }

    public function createRefund(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['required', 'exists:order,id'],
            'payment_id' => ['nullable', 'exists:payments,id'],
            'refund_amount' => ['required', 'numeric', 'gt:0'],
            'refund_reason' => ['required', 'string', 'max:1000'],
            'responsibility_type' => ['nullable', Rule::in(['company', 'sales', 'delivery', 'customer', 'other'])],
            'commission_clawback_amount' => ['sometimes', 'numeric', 'min:0'],
            'remark' => ['nullable', 'max:500'],
        ]);
        if (! empty($data['payment_id'])) {
            abort_unless(Payment::whereKey($data['payment_id'])->where('order_id', $data['order_id'])->exists(), 422, '原到账记录不属于该订单');
        }
        $data['refund_no'] = $this->number('REF');
        $data['requested_by'] = Auth::id();

        return Refund::create($data)->load('order.customer', 'payment');
    }

    public function processRefund(Request $request)
    {
        $data = $request->validate(['id' => ['required', 'exists:refunds,id'], 'status' => ['required', Rule::in(['approved', 'rejected', 'refunded', 'cancelled'])], 'remark' => ['nullable', 'max:500']]);

        return DB::transaction(function () use ($data) {
            $refund = Refund::lockForUpdate()->findOrFail($data['id']);
            $values = ['status' => $data['status'], 'remark' => $data['remark'] ?? $refund->remark];
            if ($data['status'] === 'approved') {
                $values['approved_by'] = Auth::id();
                $values['approved_at'] = now();
            }
            if ($data['status'] === 'refunded') {
                $values['refunded_at'] = now();
            }
            $refund->update($values);
            Order::whereKey($refund->order_id)->update(['refund_status' => $data['status']]);
            $this->syncOrderFinance($refund->order_id);

            return $refund->fresh()->load('order.customer', 'payment');
        });
    }

    private function syncOrderFinance(int $orderId): void
    {
        $order = Order::lockForUpdate()->findOrFail($orderId);
        $confirmed = (float) Payment::where('order_id', $orderId)->where('confirmation_status', 'confirmed')->sum('amount');
        $refunded = (float) Refund::where('order_id', $orderId)->where('status', 'refunded')->sum('refund_amount');
        $netPaid = max(0, $confirmed - $refunded);
        $order->update(['paid_amount' => $netPaid, 'receivable_amount' => max(0, (float) $order->payable_amount - $netPaid)]);

        foreach (PaymentPlan::where('order_id', $orderId)->get() as $plan) {
            $paid = (float) Payment::where('payment_plan_id', $plan->id)->where('confirmation_status', 'confirmed')->sum('amount');
            $plan->update(['paid_amount' => $paid, 'status' => $this->planStatus((float) $plan->planned_amount, $paid, $plan->due_date)]);
        }
    }

    private function planStatus(float $planned, float $paid, $dueDate): string
    {
        if ($paid >= $planned) {
            return 'paid';
        }
        if ($dueDate && now()->startOfDay()->gt($dueDate)) {
            return 'overdue';
        }

        return $paid > 0 ? 'partial' : 'pending';
    }

    private function visibleOrderIds()
    {
        $query = Order::query();
        $user = Auth::user();
        $roles = $user->roles->pluck('alias')->all();
        $isAdmin = $user->name === 'admin' || in_array('admin', $roles, true);
        if (! $isAdmin && ! array_intersect($roles, ['finance', 'sales_director'])) {
            if (in_array('sales_manager', $roles, true)) {
                $teamIds = \App\Http\Model\User::where('direct_manager_id', $user->id)->pluck('id')->push($user->id);
                $query->whereIn('sales_user_id', $teamIds);
            } else {
                $query->where(fn($q) => $q->where('sales_user_id', $user->id)->orWhere('technical_director_id', $user->id)->orWhere('optimizer_id', $user->id));
            }
        }

        return $query->select('id');
    }

    private function requireRole(array $allowed): void
    {
        abort_unless(array_intersect(Auth::user()->roles->pluck('alias')->all(), $allowed), 403, '当前角色无权执行该财务操作');
    }

    private function number(string $prefix): string
    {
        return $prefix . date('YmdHis') . Str::upper(Str::random(4));
    }
}
