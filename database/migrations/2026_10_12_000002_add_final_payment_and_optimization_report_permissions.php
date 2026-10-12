<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        ['sales.order.final_payment', '订单尾款提交', 'sales', '提交订单尾款金额和尾款截图'],
        ['delivery.optimization_report.list', '优化报告列表', 'delivery', '查看订单关联的优化数据报告列表'],
        ['delivery.optimization_report.save', '优化报告保存', 'delivery', '新增和修改订单优化数据报告'],
        ['delivery.optimization_report.delete', '优化报告删除', 'delivery', '删除订单优化数据报告'],
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::PERMISSIONS as $sort => [$name, $alias, $module, $description]) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'admin'],
                [
                    'alias' => $alias,
                    'module' => $module,
                    'permission_type' => 'button',
                    'route_path' => null,
                    'sort' => 920 - $sort,
                    'status' => 1,
                    'description' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $rolePermissions = [
            'sales_director' => ['sales.order.final_payment'],
            'sales_manager' => ['sales.order.final_payment'],
            'sales' => ['sales.order.final_payment'],
            'finance' => ['sales.order.final_payment'],
            'technical_director' => [
                'delivery.optimization_report.list',
                'delivery.optimization_report.save',
                'delivery.optimization_report.delete',
            ],
            'optimizer' => [
                'delivery.optimization_report.list',
                'delivery.optimization_report.save',
            ],
            'assistant' => ['delivery.optimization_report.list'],
        ];
        foreach ($rolePermissions as $roleAlias => $permissionNames) {
            $this->grantPermissions($roleAlias, $permissionNames);
        }

        $this->grantPermissions('admin', collect(self::PERMISSIONS)->pluck(0)->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionNames = collect(self::PERMISSIONS)->pluck(0)->all();
        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'admin')
            ->whereIn('name', $permissionNames)
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function grantPermissions(string $roleAlias, array $permissionNames): void
    {
        $roleId = DB::table('roles')->where('alias', $roleAlias)->value('id');
        if (! $roleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'admin')
            ->whereIn('name', $permissionNames)
            ->pluck('id');
        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }
};
