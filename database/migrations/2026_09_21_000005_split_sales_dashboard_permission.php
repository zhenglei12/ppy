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
            ['name' => 'sales.dashboard.view', 'guard_name' => 'admin'],
            [
                'alias' => '销售看板查看',
                'module' => 'sales',
                'permission_type' => 'menu',
                'route_path' => '/sales',
                'sort' => 995,
                'status' => 1,
                'description' => '查看销售团队排行、订单统计和待推进订单',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $permissionId = DB::table('permissions')
            ->where('name', 'sales.dashboard.view')
            ->where('guard_name', 'admin')
            ->value('id');

        if ($permissionId && DB::table('roles')->where('id', 1)->exists()) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => 1,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // 权限可能已在角色管理中重新分配，回滚时不删除现有授权。
    }
};
