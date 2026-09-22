<?php

namespace App\Http\Services;

use App\Http\Model\Department;
use App\Http\Model\Order;
use App\Http\Model\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OrderAccessService
{
    private ?array $rolesCache = null;

    private ?array $salesUserIdsCache = null;

    private ?array $deliveryUserIdsCache = null;

    public function applyScope(Builder $query): Builder
    {
        $user = Auth::user();
        $roles = $this->roles();
        if ($this->isAdmin() || array_intersect($roles, ['finance', 'technical_director'])) {
            return $query;
        }

        $salesIds = $this->salesVisibleUserIds($user, $roles);
        $deliveryIds = $this->deliveryVisibleUserIds($user, $roles);

        return $query->where(function (Builder $scope) use ($user, $salesIds, $deliveryIds) {
            $hasScope = false;
            if ($salesIds) {
                $hasScope = true;
                $scope->where(function (Builder $sales) use ($salesIds) {
                    $sales->whereIn('sales_user_id', $salesIds)
                        ->orWhereIn('sales_manager_id', $salesIds)
                        ->orWhereHas('members', fn (Builder $member) => $member
                            ->whereIn('user_id', $salesIds)
                            ->whereIn('member_role', ['sales', 'sales_manager'])
                            ->whereNull('left_at'));
                });
            }
            if ($deliveryIds) {
                $method = $hasScope ? 'orWhere' : 'where';
                $hasScope = true;
                $scope->{$method}(function (Builder $delivery) use ($deliveryIds) {
                    $delivery->whereIn('technical_director_id', $deliveryIds)
                        ->orWhereIn('optimizer_id', $deliveryIds)
                        ->orWhereIn('owner_user_id', $deliveryIds)
                        ->orWhereHas('members', fn (Builder $member) => $member
                            ->whereIn('user_id', $deliveryIds)
                            ->whereIn('member_role', ['technical_director', 'optimizer'])
                            ->whereNull('left_at'));
                });
            }
            if (! $hasScope) {
                $scope->where(function (Builder $own) use ($user) {
                    $own->where('created_by', $user->id)
                        ->orWhere('owner_user_id', $user->id)
                        ->orWhereHas('members', fn (Builder $member) => $member->where('user_id', $user->id)->whereNull('left_at'));
                });
            }
        });
    }

    public function authorizeView(Order $order): void
    {
        abort_unless($this->applyScope(Order::query()->whereKey($order->id))->exists(), 403, '无权访问该订单');
    }

    public function canEdit(Order $order): bool
    {
        if ($this->isAdmin()) {
            return ! in_array($order->current_stage, ['completed', 'cancelled'], true);
        }
        if (! in_array($order->current_stage, ['draft', 'sales_review'], true)) {
            return false;
        }
        $roles = $this->roles();
        if (in_array('sales', $roles, true)) {
            return $order->sales_user_id === Auth::id() || $order->created_by === Auth::id();
        }
        if (array_intersect($roles, ['sales_manager', 'sales_director'])) {
            return in_array($order->sales_user_id, $this->salesVisibleUserIds(Auth::user(), $roles), true);
        }

        return false;
    }

    public function canDelete(Order $order): bool
    {
        return $order->current_stage === 'draft' && $this->canEdit($order);
    }

    public function canAssign(Order $order): bool
    {
        if ($this->isAdmin()) {
            return ! in_array($order->current_stage, ['completed', 'cancelled'], true);
        }

        if (! Auth::user()->hasPermissionTo('sales.order.assign', 'admin')) {
            return false;
        }

        $roles = $this->roles();
        if (array_intersect($roles, ['sales_manager', 'sales_director'])) {
            return in_array($order->current_stage, ['draft', 'sales_review', 'finance_confirm'], true)
                && in_array($order->sales_user_id, $this->salesVisibleUserIds(Auth::user(), $roles), true);
        }
        if (in_array('technical_director', $roles, true)) {
            return in_array($order->current_stage, ['finance_confirm', 'tech_assign', 'service'], true);
        }

        return false;
    }

    public function assertAssignableUsers(array $data): void
    {
        if ($this->isAdmin()) {
            return;
        }
        $roles = $this->roles();
        $allowed = collect(array_merge(
            $this->salesVisibleUserIds(Auth::user(), $roles),
            $this->deliveryVisibleUserIds(Auth::user(), $roles)
        ));
        if (in_array('sales', $roles, true) && Auth::user()->direct_manager_id) {
            $allowed->push((int) Auth::user()->direct_manager_id);
        }
        if (array_intersect($roles, ['sales_manager', 'sales_director'])) {
            $allowed = $allowed->merge($this->userIdsByRoles(['finance']));
        }
        if (in_array('finance', $roles, true)) {
            $allowed = $allowed->merge($this->userIdsByRoles(['technical_director']));
        }
        $allowed = $allowed->map(fn ($id) => (int) $id)->unique()->values()->all();
        $ids = collect($data)->only([
            'sales_user_id', 'sales_manager_id', 'technical_director_id', 'optimizer_id', 'owner_user_id',
        ])->filter()->values()->merge(collect($data['members'] ?? [])->pluck('user_id'))->unique()->all();
        abort_if(array_diff($ids, $allowed), 403, '不能将订单分配给当前部门管理范围之外的员工');
    }

    public function roles(): array
    {
        return $this->rolesCache ??= Auth::user()->roles->pluck('alias')->filter()->values()->all();
    }

    public function isAdmin(): bool
    {
        return Auth::user()->name === 'admin' || in_array('admin', $this->roles(), true);
    }

    /**
     * 当前登录用户在销售业务中可见的员工 ID。
     *
     * 销售仅能查看自己；销售主管和销售总监可查看本人、直属下属，
     * 以及本人部门与所有下级部门中的销售人员。
     */
    public function visibleSalesUserIds(): array
    {
        return $this->salesVisibleUserIds(Auth::user(), $this->roles());
    }

    private function salesVisibleUserIds(User $user, array $roles): array
    {
        if ($this->salesUserIdsCache !== null) {
            return $this->salesUserIdsCache;
        }
        if (in_array('sales_director', $roles, true)) {
            return $this->salesUserIdsCache = $this->managedSalesUserIds($user);
        }
        if (in_array('sales_manager', $roles, true)) {
            return $this->salesUserIdsCache = $this->managedSalesUserIds($user);
        }
        if (in_array('sales', $roles, true)) {
            return $this->salesUserIdsCache = [$user->id];
        }

        return $this->salesUserIdsCache = [];
    }

    private function deliveryVisibleUserIds(User $user, array $roles): array
    {
        if ($this->deliveryUserIdsCache !== null) {
            return $this->deliveryUserIdsCache;
        }
        if (in_array('technical_director', $roles, true)) {
            return $this->deliveryUserIdsCache = $this->managedUserIds($user);
        }
        if (in_array('optimizer', $roles, true)) {
            return $this->deliveryUserIdsCache = [$user->id];
        }

        return $this->deliveryUserIdsCache = [];
    }

    private function managedUserIds(User $user): array
    {
        $ids = collect($this->directReportTreeIds($user->id));
        $departmentIds = $this->departmentTreeIds($user->department_id);
        if ($departmentIds) {
            $ids = $ids->merge(User::whereIn('department_id', $departmentIds)->pluck('id'));
        }

        return $ids->push($user->id)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function managedSalesUserIds(User $user): array
    {
        $managedIds = $this->managedUserIds($user);
        $salesIds = User::whereIn('id', $managedIds)
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('alias', [
                'sales', 'sales_manager', 'sales_director',
            ]))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        return $salesIds->push((int) $user->id)->unique()->values()->all();
    }

    private function directReportTreeIds(int $managerId): array
    {
        $all = collect([$managerId]);
        $frontier = [$managerId];
        while ($frontier) {
            $children = User::whereIn('direct_manager_id', $frontier)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $frontier = array_values(array_diff($children, $all->all()));
            $all = $all->merge($frontier)->unique();
        }

        return $all->values()->all();
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

    private function userIdsByRoles(array $roleAliases): array
    {
        return User::whereHas('roles', fn (Builder $query) => $query->whereIn('alias', $roleAliases))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
