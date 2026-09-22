<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['name' => 'sales.order.status', 'guard_name' => 'admin'],
            [
                'alias' => '销售订单状态修改',
                'module' => 'sales',
                'permission_type' => 'button',
                'route_path' => null,
                'sort' => 940,
                'status' => 1,
                'description' => '将服务中订单修改为续费中、已完成或已取消',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $permissionId = DB::table('permissions')
            ->where('name', 'sales.order.status')
            ->where('guard_name', 'admin')
            ->value('id');
        $roleIds = DB::table('roles')->whereIn('alias', ['sales', 'sales_manager'])->pluck('id')->push(1)->unique();
        foreach ($roleIds as $roleId) {
            if (DB::table('roles')->where('id', $roleId)->exists()) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'sales.order.status')->where('guard_name', 'admin')->value('id');
        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
