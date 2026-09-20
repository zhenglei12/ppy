<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            'device-list' => '设备列表',
            'device-detail' => '设备详情',
            'device-add' => '设备添加',
            'device-update' => '设备更新',
            'device-delete' => '设备删除',
            'operation_account-list' => '运营账号列表',
            'operation_account-detail' => '运营账号详情',
            'operation_account-add' => '运营账号添加',
            'operation_account-update' => '运营账号更新',
            'operation_account-delete' => '运营账号删除',
        ];

        foreach ($permissions as $name => $alias) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'admin'],
                ['alias' => $alias, 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('guard_name', 'admin')
            ->whereIn('name', [
                'device-list',
                'device-detail',
                'device-add',
                'device-update',
                'device-delete',
                'operation_account-list',
                'operation_account-detail',
                'operation_account-add',
                'operation_account-update',
                'operation_account-delete',
            ])
            ->delete();
    }
};
