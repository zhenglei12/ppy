<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createPaymentPlans();
        $this->createPayments();
        $this->createInvoices();
        $this->createRefunds();
        $this->createDeliveryProjects();
        $this->createDeliveryMilestones();
        $this->createDeliveryTasks();
        $this->createDeliveryEvidences();
    }

    private function createPaymentPlans(): void
    {
        Schema::create('payment_plans', function (Blueprint $table) {
            $table->id()->comment('收款计划主键');
            $table->foreignId('order_id')->comment('订单ID')->constrained('order')->cascadeOnDelete();
            $table->string('plan_no', 64)->unique()->comment('收款计划编号');
            $table->unsignedSmallInteger('installment_no')->default(1)->comment('分期期次');
            $table->decimal('planned_amount', 18, 2)->comment('计划收款金额');
            $table->decimal('paid_amount', 18, 2)->default(0)->comment('已确认到账金额');
            $table->date('due_date')->index()->comment('计划到期日期');
            $table->string('status', 32)->default('pending')->index()->comment('计划状态：pending待收、partial部分到账、paid已到账、overdue逾期、cancelled取消');
            $table->foreignId('follower_user_id')->nullable()->comment('收款跟进用户ID')->constrained('users')->nullOnDelete();
            $table->dateTime('next_follow_at')->nullable()->index()->comment('下次跟进时间');
            $table->string('remark', 500)->nullable()->comment('收款计划备注');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->unique(['order_id', 'installment_no'], 'uk_payment_plan_order_installment');
        });
        DB::statement("ALTER TABLE `payment_plans` COMMENT = '订单分期收款计划表'");
    }

    private function createPayments(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id()->comment('到账记录主键');
            $table->string('payment_no', 64)->unique()->comment('到账流水编号');
            $table->foreignId('order_id')->comment('订单ID')->constrained('order')->cascadeOnDelete();
            $table->foreignId('payment_plan_id')->nullable()->comment('关联收款计划ID')->constrained('payment_plans')->nullOnDelete();
            $table->string('payer_name', 255)->comment('付款主体名称');
            $table->string('company_account', 255)->nullable()->comment('收款公司账户');
            $table->decimal('amount', 18, 2)->comment('到账金额');
            $table->dateTime('paid_at')->index()->comment('客户付款时间');
            $table->string('payment_method', 32)->nullable()->comment('付款方式：bank银行转账、wechat微信、alipay支付宝、cash现金、other其他');
            $table->string('bank_serial_no', 128)->nullable()->index()->comment('银行或渠道流水号');
            $table->string('voucher_url', 1000)->nullable()->comment('付款凭证地址');
            $table->string('confirmation_status', 32)->default('pending')->index()->comment('财务确认状态：pending待确认、confirmed已确认、rejected已退回、abnormal异常');
            $table->foreignId('confirmed_by')->nullable()->comment('财务确认用户ID')->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable()->comment('财务确认时间');
            $table->string('rejection_reason', 500)->nullable()->comment('退回或异常原因');
            $table->foreignId('created_by')->comment('提交用户ID')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->index(['order_id', 'confirmation_status'], 'idx_payment_order_status');
        });
        DB::statement("ALTER TABLE `payments` COMMENT = '订单实际到账与财务确认表'");
    }

    private function createInvoices(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id()->comment('发票记录主键');
            $table->foreignId('order_id')->comment('订单ID')->constrained('order')->cascadeOnDelete();
            $table->string('invoice_no', 64)->nullable()->unique()->comment('发票号码');
            $table->string('invoice_title', 255)->comment('发票抬头');
            $table->string('tax_no', 64)->nullable()->comment('纳税人识别号');
            $table->string('invoice_type', 32)->comment('发票类型：normal普票、special专票、electronic电子票');
            $table->decimal('invoice_amount', 18, 2)->comment('开票金额');
            $table->string('status', 32)->default('requested')->index()->comment('开票状态：requested已申请、reviewing审核中、issued已开具、voided已作废、rejected已退回');
            $table->dateTime('requested_at')->nullable()->comment('申请时间');
            $table->dateTime('issued_at')->nullable()->comment('开票时间');
            $table->string('invoice_file_url', 1000)->nullable()->comment('电子发票文件地址');
            $table->string('remark', 500)->nullable()->comment('开票备注');
            $table->foreignId('requested_by')->comment('申请用户ID')->constrained('users')->restrictOnDelete();
            $table->foreignId('issued_by')->nullable()->comment('开票用户ID')->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
        DB::statement("ALTER TABLE `invoices` COMMENT = '订单发票申请与开具记录表'");
    }

    private function createRefunds(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id()->comment('退款记录主键');
            $table->string('refund_no', 64)->unique()->comment('退款编号');
            $table->foreignId('order_id')->comment('订单ID')->constrained('order')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->comment('原到账记录ID')->constrained('payments')->nullOnDelete();
            $table->decimal('refund_amount', 18, 2)->comment('申请退款金额');
            $table->string('refund_reason', 1000)->comment('退款原因');
            $table->string('responsibility_type', 32)->nullable()->comment('责任类型：company公司、sales销售、delivery交付、customer客户、other其他');
            $table->decimal('commission_clawback_amount', 18, 2)->default(0)->comment('需冲回提成金额');
            $table->string('status', 32)->default('pending')->index()->comment('退款状态：pending待审批、approved已批准、rejected已驳回、refunded已退款、cancelled已取消');
            $table->foreignId('requested_by')->comment('退款申请用户ID')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->comment('退款审批用户ID')->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable()->comment('退款审批时间');
            $table->dateTime('refunded_at')->nullable()->comment('实际退款时间');
            $table->dateTime('hr_notified_at')->nullable()->comment('通知人事冲回时间');
            $table->string('remark', 500)->nullable()->comment('退款备注');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
        DB::statement("ALTER TABLE `refunds` COMMENT = '订单退款、责任归因与提成冲回表'");
    }

    private function createDeliveryProjects(): void
    {
        Schema::create('delivery_projects', function (Blueprint $table) {
            $table->id()->comment('交付项目主键');
            $table->string('project_no', 64)->unique()->comment('交付项目编号');
            $table->foreignId('order_id')->unique()->comment('订单ID')->constrained('order')->cascadeOnDelete();
            $table->foreignId('technical_director_id')->nullable()->comment('技术总监用户ID')->constrained('users')->nullOnDelete();
            $table->foreignId('optimizer_id')->nullable()->comment('优化师用户ID')->constrained('users')->nullOnDelete();
            $table->foreignId('assistant_id')->nullable()->comment('优化师助理用户ID')->constrained('users')->nullOnDelete();
            $table->date('planned_start_date')->nullable()->comment('计划启动日期');
            $table->date('actual_start_date')->nullable()->comment('实际启动日期');
            $table->date('planned_end_date')->nullable()->comment('计划结束日期');
            $table->date('actual_end_date')->nullable()->comment('实际结束日期');
            $table->unsignedInteger('service_days')->default(0)->comment('已服务天数');
            $table->string('current_node', 32)->default('D1')->index()->comment('当前交付节点：D1、D3、D7、D15、D30、D60、D90');
            $table->string('status', 32)->default('pending')->index()->comment('项目状态：pending待启动、active执行中、paused暂停、completed已完成、terminated已终止');
            $table->string('health_status', 16)->default('green')->index()->comment('项目健康状态：green正常、yellow预警、red风险');
            $table->text('success_criteria')->nullable()->comment('项目成功标准');
            $table->json('baseline_data')->nullable()->comment('项目基线数据JSON');
            $table->json('key_keywords')->nullable()->comment('重点问题词或关键词JSON');
            $table->text('risk_summary')->nullable()->comment('交付风险说明');
            $table->dateTime('renewal_warning_at')->nullable()->index()->comment('续费预警时间');
            $table->foreignId('created_by')->comment('创建用户ID')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->softDeletes()->comment('逻辑删除时间');
        });
        DB::statement("ALTER TABLE `delivery_projects` COMMENT = '订单优化交付项目主表'");
    }

    private function createDeliveryMilestones(): void
    {
        Schema::create('delivery_milestones', function (Blueprint $table) {
            $table->id()->comment('交付节点主键');
            $table->foreignId('project_id')->comment('交付项目ID')->constrained('delivery_projects')->cascadeOnDelete();
            $table->string('milestone_code', 32)->comment('节点编码：D1、D3、D7、D15、D30、D60、D90');
            $table->string('milestone_name', 128)->comment('节点名称');
            $table->unsignedSmallInteger('sequence_no')->comment('节点顺序');
            $table->dateTime('planned_at')->nullable()->index()->comment('计划完成时间');
            $table->dateTime('completed_at')->nullable()->comment('实际完成时间');
            $table->string('status', 32)->default('pending')->index()->comment('节点状态：pending待开始、processing进行中、reviewing待验收、completed已完成、overdue已超时');
            $table->text('acceptance_criteria')->nullable()->comment('节点验收标准');
            $table->text('review_result')->nullable()->comment('节点复盘与验收结论');
            $table->foreignId('owner_user_id')->nullable()->comment('节点责任用户ID')->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->comment('节点验收用户ID')->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->unique(['project_id', 'milestone_code'], 'uk_delivery_project_milestone');
        });
        DB::statement("ALTER TABLE `delivery_milestones` COMMENT = '交付项目D1至D90节点表'");
    }

    private function createDeliveryTasks(): void
    {
        Schema::create('delivery_tasks', function (Blueprint $table) {
            $table->id()->comment('交付任务主键');
            $table->string('task_no', 64)->unique()->comment('任务编号');
            $table->foreignId('project_id')->comment('交付项目ID')->constrained('delivery_projects')->cascadeOnDelete();
            $table->foreignId('milestone_id')->nullable()->comment('所属交付节点ID')->constrained('delivery_milestones')->nullOnDelete();
            $table->string('task_type', 32)->index()->comment('任务类型：material资料、content内容、publish发布、review复盘、meeting会议、other其他');
            $table->string('title', 255)->comment('任务标题');
            $table->text('description')->nullable()->comment('任务说明');
            $table->text('task_standard')->nullable()->comment('任务验收标准');
            $table->string('template_name', 255)->nullable()->comment('使用的任务模板名称');
            $table->foreignId('assignee_id')->comment('任务执行用户ID')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewer_id')->nullable()->comment('任务审核用户ID')->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('priority')->default(2)->index()->comment('优先级：1低、2普通、3高、4紧急');
            $table->string('status', 32)->default('pending')->index()->comment('任务状态：pending待处理、processing进行中、reviewing待审核、completed已完成、returned已退回、cancelled已取消');
            $table->dateTime('due_at')->nullable()->index()->comment('任务截止时间');
            $table->dateTime('completed_at')->nullable()->comment('任务完成时间');
            $table->unsignedInteger('revision_count')->default(0)->comment('修改或退回次数');
            $table->string('return_reason', 1000)->nullable()->comment('任务退回原因');
            $table->string('result_url', 1000)->nullable()->comment('任务结果链接');
            $table->foreignId('created_by')->comment('创建用户ID')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->softDeletes()->comment('逻辑删除时间');
            $table->index(['assignee_id', 'status', 'due_at'], 'idx_delivery_task_assignee_status_due');
        });
        DB::statement("ALTER TABLE `delivery_tasks` COMMENT = '优化师及助理交付任务表'");
    }

    private function createDeliveryEvidences(): void
    {
        Schema::create('delivery_evidences', function (Blueprint $table) {
            $table->id()->comment('交付凭证主键');
            $table->foreignId('project_id')->comment('交付项目ID')->constrained('delivery_projects')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->comment('交付任务ID')->constrained('delivery_tasks')->cascadeOnDelete();
            $table->string('evidence_type', 32)->comment('凭证类型：link链接、image截图、file文件、report报告、record记录');
            $table->string('title', 255)->comment('凭证标题');
            $table->string('file_url', 1000)->comment('凭证文件或链接地址');
            $table->string('version_no', 32)->nullable()->comment('凭证版本号');
            $table->string('status', 32)->default('submitted')->index()->comment('凭证状态：submitted已提交、approved已通过、rejected已退回');
            $table->text('description')->nullable()->comment('凭证说明');
            $table->foreignId('submitted_by')->comment('提交用户ID')->constrained('users')->restrictOnDelete();
            $table->dateTime('submitted_at')->useCurrent()->comment('提交时间');
            $table->foreignId('reviewed_by')->nullable()->comment('审核用户ID')->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable()->comment('审核时间');
            $table->string('review_note', 500)->nullable()->comment('审核意见');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
        DB::statement("ALTER TABLE `delivery_evidences` COMMENT = '交付任务完成凭证与审核记录表'");
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_evidences');
        Schema::dropIfExists('delivery_tasks');
        Schema::dropIfExists('delivery_milestones');
        Schema::dropIfExists('delivery_projects');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_plans');
    }
};
