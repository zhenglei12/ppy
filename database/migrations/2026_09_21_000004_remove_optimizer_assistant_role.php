<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('alias', 'assistant')->value('id');

        if ($roleId) {
            // 清理角色授权和用户绑定；订单、交付项目中的历史 assistant_id 保留。
            DB::table('model_has_roles')->where('role_id', $roleId)->delete();
            DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }

        Artisan::call('permission:cache-reset');
    }

    public function down(): void
    {
        // 该角色已停用，回滚不重新创建业务角色。
    }
};
