<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order', function (Blueprint $table) {
            $table->decimal('final_payment_amount', 18, 2)->default(0)->after('receivable_amount')->comment('尾款金额');
            $table->json('final_payment_screenshot_files')->nullable()->after('final_payment_amount')->comment('尾款截图地址JSON数组');
            $table->decimal('enterprise_target_mention_rate', 5, 2)->nullable()->after('final_payment_screenshot_files')->comment('企业目标提及率（百分比，0-100）');
        });

        Schema::create('order_optimization_reports', function (Blueprint $table) {
            $table->id()->comment('订单优化报告主键');
            $table->foreignId('order_id')->comment('订单ID')->constrained('order')->cascadeOnDelete();
            $table->string('project_name', 255)->comment('项目名称');
            $table->string('service_cycle', 32)->comment('服务周期：one_month一个月、three_months三个月、annual年度');
            $table->string('optimization_stage', 32)->comment('当前优化阶段：first第一阶段、second第二阶段、third第三阶段');
            $table->text('core_work_content')->nullable()->comment('核心工作内容');
            $table->decimal('current_mention_rate', 5, 2)->nullable()->comment('目前提及率（百分比，0-100）');
            $table->dateTime('semi_monthly_report_at')->nullable()->index()->comment('半月数据汇报时间');
            $table->text('optimization_difficulties_and_solutions')->nullable()->comment('目前优化难点及解决办法');
            $table->foreignId('publisher_user_id')->comment('发文人（优化师）用户ID')->constrained('users')->restrictOnDelete();
            $table->dateTime('data_report_at')->nullable()->index()->comment('数据报告时间');
            $table->foreignId('created_by')->comment('创建用户ID')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->index(['order_id', 'data_report_at'], 'idx_order_optimization_report_time');
        });
        DB::statement("ALTER TABLE `order_optimization_reports` COMMENT = '订单优化数据报告表'");

    }

    public function down(): void
    {
        Schema::dropIfExists('order_optimization_reports');
        Schema::table('order', function (Blueprint $table) {
            $table->dropColumn([
                'final_payment_amount',
                'final_payment_screenshot_files',
                'enterprise_target_mention_rate',
            ]);
        });
    }
};
