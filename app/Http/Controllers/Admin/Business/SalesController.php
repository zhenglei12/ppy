<?php

namespace App\Http\Controllers\Admin\Business;

use App\Http\Controllers\Controller;
use App\Http\Model\Customer;
use App\Http\Model\Order;
use App\Http\Services\OrderAccessService;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    public function __construct(private OrderAccessService $access)
    {
    }

    public function dashboard()
    {
        $query = Order::query();
        $this->access->applyScope($query);
        $orders = (clone $query)->whereNotIn('business_status', ['cancelled']);
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $stageDistribution = (clone $orders)->select('current_stage', DB::raw('count(*) as total'))
            ->groupBy('current_stage')->pluck('total', 'current_stage');
        $healthDistribution = (clone $orders)->select('health_status', DB::raw('count(*) as total'))
            ->groupBy('health_status')->pluck('total', 'health_status');
        $ranking = (clone $orders)->select('sales_user_id', DB::raw('count(*) as order_count'), DB::raw('sum(payable_amount) as payable_amount'))
            ->whereNotNull('sales_user_id')->groupBy('sales_user_id')->orderByDesc('order_count')->limit(10)
            ->with('salesUser:id,name,department_id')->get();

        $customerQuery = Customer::query()->whereBetween('created_at', [$monthStart, $monthEnd]);
        $roles = $this->access->roles();
        if (! $this->access->isAdmin() && ! array_intersect($roles, ['finance', 'technical_director'])) {
            $visibleSalesUserIds = $this->access->visibleSalesUserIds();
            $customerQuery->where(function ($query) use ($visibleSalesUserIds) {
                $query->whereIn('owner_user_id', $visibleSalesUserIds)
                    ->orWhereIn('co_owner_user_id', $visibleSalesUserIds);
            });
        }

        return [
            'order_count' => (clone $orders)->count(),
            'month_order_count' => (clone $orders)->whereBetween('created_at', [$monthStart, $monthEnd])->count(),
            'payable_amount' => (clone $orders)->sum('payable_amount'),
            'paid_amount' => (clone $orders)->sum('paid_amount'),
            'receivable_amount' => (clone $orders)->sum('receivable_amount'),
            'pending_review_count' => (clone $orders)->whereIn('current_stage', ['draft', 'sales_review'])->count(),
            'stalled_count' => (clone $orders)->whereNotNull('next_action_at')->where('next_action_at', '<', now())->count(),
            'new_customer_count' => $customerQuery->count(),
            'stage_distribution' => $stageDistribution,
            'health_distribution' => $healthDistribution,
            'ranking' => $ranking,
            'pending_orders' => (clone $orders)->with(['customer:id,legal_name,brand_name', 'salesUser:id,name'])
                ->whereIn('current_stage', ['draft', 'sales_review'])->latest('updated_at')->limit(10)->get(),
        ];
    }
}
