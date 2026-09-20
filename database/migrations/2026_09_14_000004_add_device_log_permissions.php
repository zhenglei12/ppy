<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            'device-log.list' => '设备修改日志',
            'operation_account-log.list' => '运营账号修改日志',
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
            ->whereIn('name', ['device-log.list', 'operation_account-log.list'])
            ->delete();
    }
};
