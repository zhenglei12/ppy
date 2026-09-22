<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order')) {
            return;
        }

        Schema::table('order', function (Blueprint $table) {
            if (! Schema::hasColumn('order', 'customer_legal_name')) {
                $table->string('customer_legal_name', 255)->nullable()->after('customer_id')->comment('客户主体全称');
            }
            if (! Schema::hasColumn('order', 'customer_credit_code')) {
                $table->string('customer_credit_code', 32)->nullable()->after('customer_legal_name')->comment('统一社会信用代码');
            }
            if (! Schema::hasColumn('order', 'customer_mobile')) {
                $table->string('customer_mobile', 32)->nullable()->after('customer_credit_code')->comment('客户联系电话');
            }
            if (! Schema::hasColumn('order', 'customer_wechat')) {
                $table->string('customer_wechat', 64)->nullable()->after('customer_mobile')->comment('客户微信号');
            }
            if (! Schema::hasColumn('order', 'customer_industry')) {
                $table->string('customer_industry', 128)->nullable()->after('customer_wechat')->comment('客户所属行业');
            }
        });

        $remove = [
            'product_name', 'discount_amount', 'payment_terms', 'service_objective',
            'materials_due_date', 'success_criteria', 'baseline_data', 'required_materials',
            'kickoff_attendees', 'refund_terms', 'pending_verification',
            'special_delivery_terms', 'risk_summary',
        ];
        foreach ($remove as $column) {
            if (Schema::hasColumn('order', $column)) {
                Schema::table('order', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }

    public function down(): void
    {
        // 业务字段删除不可安全恢复，回滚仅移除本迁移新增的客户快照字段。
        foreach (['customer_industry', 'customer_wechat', 'customer_mobile', 'customer_credit_code', 'customer_legal_name'] as $column) {
            if (Schema::hasColumn('order', $column)) {
                Schema::table('order', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
