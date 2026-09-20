<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $legacyOrderTable = 'legacy_order_20260917';

    public function up(): void
    {
        $this->createCustomers();
        $this->createContacts();

        // 原 order 是论文订单结构。先改名归档，再创建总部经营系统的新 order。
        if (Schema::hasTable('order') && !Schema::hasTable($this->legacyOrderTable)) {
            Schema::rename('order', $this->legacyOrderTable);
            DB::statement("ALTER TABLE `{$this->legacyOrderTable}` COMMENT = '旧论文订单归档表，经营系统不再使用'");
        }

        if (!Schema::hasTable('order')) {
            Schema::create('order', function (Blueprint $table) {
                $table->id()->comment('订单主键');
                $table->string('order_no', 64)->unique()->comment('订单编号');
                $table->foreignId('customer_id')->comment('客户ID')->constrained('crm_customers')->restrictOnDelete();
                $table->foreignId('contact_id')->nullable()->comment('主要联系人ID')->constrained('crm_contacts')->nullOnDelete();
                $table->string('product_type', 32)->index()->comment('产品类型：trial体验版、annual年度服务、other其他');
                $table->string('product_name', 255)->comment('产品或服务名称');
                $table->decimal('contract_amount', 18, 2)->default(0)->comment('合同原始金额');
                $table->decimal('discount_amount', 18, 2)->default(0)->comment('折扣金额');
                $table->decimal('payable_amount', 18, 2)->default(0)->comment('客户应付金额');
                $table->decimal('paid_amount', 18, 2)->default(0)->comment('财务已确认到账金额');
                $table->decimal('receivable_amount', 18, 2)->default(0)->comment('应收余额');
                $table->string('payment_terms', 1000)->nullable()->comment('付款条件');
                $table->foreignId('sales_user_id')->comment('主销售用户ID')->constrained('users')->restrictOnDelete();
                $table->foreignId('sales_manager_id')->nullable()->comment('销售主管用户ID')->constrained('users')->nullOnDelete();
                $table->foreignId('technical_director_id')->nullable()->comment('技术总监用户ID')->constrained('users')->nullOnDelete();
                $table->foreignId('optimizer_id')->nullable()->comment('优化师用户ID')->constrained('users')->nullOnDelete();
                $table->foreignId('assistant_id')->nullable()->comment('优化师助理用户ID')->constrained('users')->nullOnDelete();
                $table->string('current_stage', 32)->default('draft')->index()->comment('当前阶段：draft草稿、sales_review销售审核、finance_confirm财务确认、tech_assign技术分单、service服务中、renewal续费中、completed完成、cancelled取消');
                $table->string('business_status', 32)->default('pending')->index()->comment('订单状态：pending待处理、active进行中、completed已完成、cancelled已取消');
                $table->string('health_status', 16)->default('green')->index()->comment('健康状态：green正常、yellow预警、red风险');
                $table->foreignId('owner_user_id')->comment('当前责任用户ID')->constrained('users')->restrictOnDelete();
                $table->string('next_action', 500)->nullable()->comment('下一步动作');
                $table->dateTime('next_action_at')->nullable()->index()->comment('下一步动作截止时间');
                $table->date('expected_start_date')->nullable()->comment('预计启动日期');
                $table->date('actual_start_date')->nullable()->comment('实际启动日期');
                $table->unsignedInteger('service_cycle_days')->nullable()->comment('服务周期天数');
                $table->date('expected_end_date')->nullable()->comment('预计结束日期');
                $table->string('customer_owner_name', 100)->nullable()->comment('客户方负责人');
                $table->string('primary_business', 255)->nullable()->comment('主推业务');
                $table->string('target_region', 255)->nullable()->comment('重点目标区域');
                $table->text('service_objective')->nullable()->comment('服务目标与成功标准');
                $table->text('sales_commitment')->nullable()->comment('销售对客户的口头或书面承诺');
                $table->text('risk_summary')->nullable()->comment('承诺、退款、资质或交付风险说明');
                $table->dateTime('submitted_at')->nullable()->comment('提交审核时间');
                $table->dateTime('completed_at')->nullable()->comment('订单完成时间');
                $table->foreignId('created_by')->comment('创建用户ID')->constrained('users')->restrictOnDelete();
                $table->timestamp('created_at')->nullable()->comment('创建时间');
                $table->timestamp('updated_at')->nullable()->comment('更新时间');
                $table->softDeletes()->comment('逻辑删除时间');

                $table->index(['sales_user_id', 'current_stage'], 'idx_order_sales_stage');
                $table->index(['owner_user_id', 'next_action_at'], 'idx_order_owner_next');
                $table->index(['health_status', 'business_status'], 'idx_order_health_status');
            });

            DB::statement("ALTER TABLE `order` COMMENT = '总部经营订单主表：串联销售、财务、交付和续费'");
        }

        $this->createOrderMembers();
        $this->createOrderStageLogs();
        $this->createContracts();
    }

    private function createCustomers(): void
    {
        if (Schema::hasTable('crm_customers')) {
            return;
        }

        Schema::create('crm_customers', function (Blueprint $table) {
            $table->id()->comment('客户主键');
            $table->string('customer_no', 64)->unique()->comment('客户编号');
            $table->string('legal_name', 255)->comment('客户主体全称');
            $table->string('credit_code', 32)->nullable()->unique()->comment('统一社会信用代码');
            $table->string('brand_name', 128)->nullable()->comment('品牌名称');
            $table->string('industry', 128)->nullable()->index()->comment('所属行业');
            $table->string('province', 64)->nullable()->comment('省份');
            $table->string('city', 64)->nullable()->comment('城市');
            $table->string('district', 64)->nullable()->comment('区县');
            $table->string('address', 255)->nullable()->comment('详细地址');
            $table->string('source', 64)->nullable()->index()->comment('线索来源');
            $table->string('customer_level', 8)->nullable()->index()->comment('客户分层：A、B、C');
            $table->foreignId('owner_user_id')->comment('归属销售用户ID')->constrained('users')->restrictOnDelete();
            $table->foreignId('co_owner_user_id')->nullable()->comment('协作销售用户ID')->constrained('users')->nullOnDelete();
            $table->string('attribution_reason', 255)->nullable()->comment('客户归属依据');
            $table->boolean('contact_authorized')->default(false)->comment('是否取得联系授权');
            $table->boolean('material_authorized')->default(false)->comment('是否取得素材授权');
            $table->string('status', 32)->default('lead')->index()->comment('客户状态：lead线索、opportunity商机、customer客户、in_service服务中、lost流失');
            $table->dateTime('next_follow_at')->nullable()->index()->comment('下次跟进时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->softDeletes()->comment('逻辑删除时间');
            $table->index(['owner_user_id', 'status'], 'idx_customer_owner_status');
        });

        DB::statement("ALTER TABLE `crm_customers` COMMENT = '客户主体表'");
    }

    private function createContacts(): void
    {
        if (Schema::hasTable('crm_contacts')) {
            return;
        }

        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id()->comment('联系人主键');
            $table->foreignId('customer_id')->comment('所属客户ID')->constrained('crm_customers')->cascadeOnDelete();
            $table->string('contact_name', 64)->comment('联系人姓名');
            $table->string('mobile', 32)->nullable()->index()->comment('联系人手机号');
            $table->string('position_name', 128)->nullable()->comment('联系人职位');
            $table->string('wechat_no', 64)->nullable()->comment('微信号');
            $table->string('email', 128)->nullable()->comment('电子邮箱');
            $table->boolean('is_primary')->default(false)->comment('是否主要联系人');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->softDeletes()->comment('逻辑删除时间');
            $table->index(['customer_id', 'is_primary'], 'idx_contact_customer_primary');
        });

        DB::statement("ALTER TABLE `crm_contacts` COMMENT = '客户联系人表'");
    }

    private function createOrderMembers(): void
    {
        if (Schema::hasTable('order_members')) {
            return;
        }

        Schema::create('order_members', function (Blueprint $table) {
            $table->id()->comment('订单成员主键');
            $table->foreignId('order_id')->comment('订单ID')->constrained('order')->cascadeOnDelete();
            $table->foreignId('user_id')->comment('参与用户ID')->constrained('users')->restrictOnDelete();
            $table->string('member_role', 32)->comment('成员角色：sales销售、sales_manager销售主管、technical_director技术总监、optimizer优化师、assistant助理');
            $table->decimal('commission_ratio', 7, 4)->default(0)->comment('提成比例');
            $table->dateTime('joined_at')->useCurrent()->comment('加入订单时间');
            $table->dateTime('left_at')->nullable()->comment('退出订单时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->unique(['order_id', 'user_id', 'member_role'], 'uk_order_member_role');
        });

        DB::statement("ALTER TABLE `order_members` COMMENT = '订单责任链与协作成员表'");
    }

    private function createOrderStageLogs(): void
    {
        if (Schema::hasTable('order_stage_logs')) {
            return;
        }

        Schema::create('order_stage_logs', function (Blueprint $table) {
            $table->id()->comment('阶段记录主键');
            $table->foreignId('order_id')->comment('订单ID')->constrained('order')->cascadeOnDelete();
            $table->string('from_stage', 32)->nullable()->comment('变更前阶段');
            $table->string('to_stage', 32)->comment('变更后阶段');
            $table->foreignId('owner_user_id')->comment('阶段责任用户ID')->constrained('users')->restrictOnDelete();
            $table->string('next_action', 500)->nullable()->comment('下一步动作');
            $table->dateTime('deadline_at')->nullable()->index()->comment('阶段截止时间');
            $table->text('evidence_note')->nullable()->comment('阶段完成凭证说明');
            $table->foreignId('operated_by')->comment('操作用户ID')->constrained('users')->restrictOnDelete();
            $table->dateTime('operated_at')->useCurrent()->comment('操作时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->index(['order_id', 'operated_at'], 'idx_order_stage_time');
        });

        DB::statement("ALTER TABLE `order_stage_logs` COMMENT = '订单阶段流转记录表'");
    }

    private function createContracts(): void
    {
        if (Schema::hasTable('contracts')) {
            return;
        }

        Schema::create('contracts', function (Blueprint $table) {
            $table->id()->comment('合同主键');
            $table->foreignId('order_id')->comment('订单ID')->constrained('order')->cascadeOnDelete();
            $table->string('contract_no', 64)->unique()->comment('合同编号');
            $table->string('contract_name', 255)->comment('合同名称');
            $table->decimal('contract_amount', 18, 2)->comment('合同金额');
            $table->text('payment_terms')->nullable()->comment('付款条款');
            $table->date('sign_date')->nullable()->comment('签署日期');
            $table->date('start_date')->nullable()->comment('合同开始日期');
            $table->date('end_date')->nullable()->index()->comment('合同结束日期');
            $table->string('customer_signer', 64)->nullable()->comment('客户签署人');
            $table->string('company_signer', 64)->nullable()->comment('公司签署人');
            $table->string('status', 32)->default('draft')->index()->comment('合同状态：draft草稿、reviewing审核中、signed已签署、active生效、expired到期、terminated终止');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });

        DB::statement("ALTER TABLE `contracts` COMMENT = '订单合同表'");
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('order_stage_logs');
        Schema::dropIfExists('order_members');
        Schema::dropIfExists('order');

        if (Schema::hasTable($this->legacyOrderTable)) {
            Schema::rename($this->legacyOrderTable, 'order');
        }

        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_customers');
    }
};
