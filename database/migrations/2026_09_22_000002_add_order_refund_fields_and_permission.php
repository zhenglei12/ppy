<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order', function (Blueprint $table) {
            $table->decimal('refund_amount', 18, 2)->default(0)->after('receivable_amount')->comment('订单售后申请退款金额');
            $table->text('refund_reason')->nullable()->after('refund_amount')->comment('订单售后退款原因');
            $table->string('refund_status', 32)->nullable()->index()->after('refund_reason')->comment('退款状态：pending待处理、approved已批准、rejected已驳回、refunded已退款、cancelled已取消');
            $table->json('refund_screenshot_files')->nullable()->after('refund_status')->comment('退款截图地址JSON数组');
            $table->dateTime('refund_applied_at')->nullable()->after('refund_screenshot_files')->comment('售后退款申请时间');
        });

        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['name' => 'sales.order.refund', 'guard_name' => 'admin'],
            [
                'alias' => '销售订单售后退款', 'module' => 'sales', 'permission_type' => 'button',
                'route_path' => null, 'sort' => 935, 'status' => 1,
                'description' => '为已完成订单发起售后退款并上传退款截图',
                'created_at' => $now, 'updated_at' => $now,
            ]
        );
        $permissionId = DB::table('permissions')->where('name', 'sales.order.refund')->where('guard_name', 'admin')->value('id');
        $roleIds = DB::table('roles')->whereIn('alias', ['sales', 'sales_manager'])->pluck('id')->push(1)->unique();
        foreach ($roleIds as $roleId) {
            if (DB::table('roles')->where('id', $roleId)->exists()) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'sales.order.refund')->where('guard_name', 'admin')->value('id');
        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
        Schema::table('order', function (Blueprint $table) {
            $table->dropIndex(['refund_status']);
            $table->dropColumn(['refund_amount', 'refund_reason', 'refund_status', 'refund_screenshot_files', 'refund_applied_at']);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
