<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $rolePermissions = [
            'technical_director' => ['sales.order.view', 'sales.order.assign'],
            'optimizer' => ['sales.order.view'],
            'assistant' => ['sales.order.view'],
        ];

        foreach ($rolePermissions as $roleAlias => $permissionNames) {
            $roleId = DB::table('roles')->where('alias', $roleAlias)->value('id');
            if (! $roleId) {
                continue;
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

        if (DB::table('roles')->where('id', 1)->exists()) {
            foreach (DB::table('permissions')->where('name', 'like', 'sales.order.%')->pluck('id') as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => 1,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // 角色权限可能已被管理员再次调整，回滚时不主动删除现有授权。
    }
};
