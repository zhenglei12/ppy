<?php

namespace App\Http\Controllers\Admin\Business;

use App\Http\Controllers\Controller;
use App\Http\Model\DeliveryProject;
use App\Http\Model\DeliveryTask;
use App\Http\Model\Order;
use App\Http\Model\Payment;
use App\Http\Model\PaymentPlan;
use App\Http\Model\User;
use App\Http\Services\OrderAccessService;
use Illuminate\Support\Facades\DB;

class OverviewController extends Controller
{
    public function __construct(private OrderAccessService $access)
    {
    }

    public function dashboard()
    {
        $orders = Order::query();
        $this->access->applyScope($orders);
        $orderIds = (clone $orders)->select('id');
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $projects = DeliveryProject::whereIn('order_id', $orderIds);
        $activeOrders = (clone $orders)->whereNotIn('business_status', ['cancelled']);

        return [
            'metrics' => [
                'confirmed_amount' => Payment::whereIn('order_id', $orderIds)->where('confirmation_status', 'confirmed')->whereBetween('paid_at', [$monthStart, $monthEnd])->sum('amount'),
                'new_customer_count' => $this->visibleNewCustomerCount($monthStart, $monthEnd),
                'active_project_count' => (clone $projects)->where('status', 'active')->count(),
                'renewal_risk_count' => (clone $projects)->whereNotNull('renewal_warning_at')->whereBetween('renewal_warning_at', [now(), now()->addDays(30)])->count(),
            ],
            'flow' => [
                ['title' => '销售个人入单', 'desc' => '客户、合同、付款、承诺'],
                ['title' => '销售总监审核', 'desc' => '归属、报价、风险、完整性'],
                ['title' => '财务确认', 'desc' => '到账、应收、发票、退款'],
                ['title' => '技术总监分单', 'desc' => '优化师、容量、启动日'],
                ['title' => '交付与续费', 'desc' => '节点、证据、风险、回款'],
            ],
            'people' => [
                'active_count' => User::where('employment_status', 'active')->count(),
                'month_hire_count' => User::whereBetween('hire_date', [$monthStart->toDateString(), $monthEnd->toDateString()])->count(),
                'probation_count' => User::where('employment_status', 'probation')->count(),
            ],
            'orders' => [
                'count' => (clone $activeOrders)->count(),
                'payable_amount' => (clone $activeOrders)->sum('payable_amount'),
                'receivable_amount' => (clone $activeOrders)->sum('receivable_amount'),
                'stage_distribution' => (clone $activeOrders)->select('current_stage', DB::raw('count(*) as total'))->groupBy('current_stage')->pluck('total', 'current_stage'),
            ],
            'finance' => [
                'confirmed_amount' => Payment::whereIn('order_id', $orderIds)->where('confirmation_status', 'confirmed')->sum('amount'),
                'receivable_amount' => (clone $activeOrders)->sum('receivable_amount'),
                'overdue_amount' => PaymentPlan::whereIn('order_id', $orderIds)->whereIn('status', ['pending', 'partial', 'overdue'])->whereDate('due_date', '<', today())->sum(DB::raw('planned_amount - paid_amount')),
            ],
            'delivery' => [
                'active_project_count' => (clone $projects)->where('status', 'active')->count(),
                'overdue_task_count' => DeliveryTask::whereIn('project_id', (clone $projects)->select('id'))->whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', now())->count(),
                'risk_count' => (clone $projects)->whereIn('health_status', ['yellow', 'red'])->count(),
            ],
        ];
    }

    private function visibleNewCustomerCount($start, $end): int
    {
        $query = \App\Http\Model\Customer::query()->whereBetween('created_at', [$start, $end]);
        $roles = auth()->user()->roles->pluck('alias')->all();
        if (! array_intersect($roles, ['admin', 'finance', 'sales_director', 'technical_director'])) {
            $query->where(fn ($q) => $q->where('owner_user_id', auth()->id())->orWhere('co_owner_user_id', auth()->id()));
        }

        return $query->count();
    }
}
