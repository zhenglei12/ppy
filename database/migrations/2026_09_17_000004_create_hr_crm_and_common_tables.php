<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createCrmFollowups();
        $this->createProbationReviews();
        $this->createPerformanceSchemes();
        $this->createPerformanceRecords();
        $this->createAttendanceMonthlies();
        $this->createPayrollSheets();
        $this->createPayrollItems();
        $this->createCrmCallRecords();
        $this->createApprovalInstances();
        $this->createApprovalRecords();
        $this->createAttachments();
        $this->createReminders();
        $this->createOperationLogs();
    }

    private function createCrmFollowups(): void
    {
        Schema::create('crm_followups', function (Blueprint $table) {
            $table->id()->comment('跟进记录主键');
            $table->foreignId('customer_id')->comment('客户ID')->constrained('crm_customers')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->comment('关联订单ID')->constrained('order')->nullOnDelete();
            $table->foreignId('user_id')->comment('跟进用户ID')->constrained('users')->restrictOnDelete();
            $table->string('followup_method', 32)->comment('跟进方式：call电话、wechat微信、visit拜访、meeting会议、other其他');
            $table->string('customer_stage', 32)->nullable()->index()->comment('跟进后客户阶段');
            $table->text('followup_result')->comment('本次跟进结果');
            $table->string('customer_feedback', 1000)->nullable()->comment('客户反馈');
            $table->decimal('expected_amount', 18, 2)->nullable()->comment('预计成交金额');
            $table->string('next_action', 500)->nullable()->comment('下一步动作');
            $table->dateTime('next_follow_at')->nullable()->index()->comment('下次跟进时间');
            $table->boolean('is_valid_contact')->default(false)->comment('是否有效触达');
            $table->dateTime('followed_at')->useCurrent()->index()->comment('实际跟进时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->index(['user_id', 'followed_at'], 'idx_followup_user_time');
        });
        DB::statement("ALTER TABLE `crm_followups` COMMENT = '销售与电销客户跟进记录表'");
    }

    private function createProbationReviews(): void
    {
        Schema::create('probation_reviews', function (Blueprint $table) {
            $table->id()->comment('试用期评估主键');
            $table->foreignId('user_id')->comment('试用员工用户ID')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('checkpoint_day')->comment('评估节点天数：3、7、15、30');
            $table->date('due_date')->index()->comment('评估截止日期');
            $table->foreignId('owner_user_id')->comment('评估负责人用户ID')->constrained('users')->restrictOnDelete();
            $table->boolean('training_completed')->default(false)->comment('是否完成培训');
            $table->decimal('simulation_score', 6, 2)->nullable()->comment('模拟通关得分');
            $table->decimal('practice_score', 6, 2)->nullable()->comment('实战验收得分');
            $table->json('evidence_data')->nullable()->comment('业务数据与行为证据JSON');
            $table->string('warning_reason', 1000)->nullable()->comment('预警原因');
            $table->text('improvement_plan')->nullable()->comment('改进计划');
            $table->string('result', 32)->nullable()->comment('评估结果：pass通过、extend延期、transfer转岗、fail不通过');
            $table->string('status', 32)->default('pending')->index()->comment('评估状态：pending待评估、reviewing复核中、completed已完成');
            $table->foreignId('reviewed_by')->nullable()->comment('复核用户ID')->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable()->comment('复核时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->unique(['user_id', 'checkpoint_day'], 'uk_probation_user_checkpoint');
        });
        DB::statement("ALTER TABLE `probation_reviews` COMMENT = '员工试用期3至30天节点评估表'");
    }

    private function createPerformanceSchemes(): void
    {
        Schema::create('performance_schemes', function (Blueprint $table) {
            $table->id()->comment('绩效方案主键');
            $table->string('scheme_name', 255)->comment('绩效方案名称');
            $table->string('version_no', 32)->comment('绩效方案版本');
            $table->string('applicable_role', 64)->nullable()->index()->comment('适用角色标识');
            $table->string('period_type', 16)->default('monthly')->comment('考核周期：monthly月度、quarterly季度、yearly年度');
            $table->json('metrics_config')->comment('绩效指标、权重与数据源配置JSON');
            $table->date('effective_date')->comment('生效日期');
            $table->date('expire_date')->nullable()->comment('失效日期');
            $table->unsignedTinyInteger('status')->default(1)->index()->comment('方案状态：0停用、1启用');
            $table->foreignId('created_by')->comment('创建用户ID')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->unique(['scheme_name', 'version_no'], 'uk_performance_scheme_version');
        });
        DB::statement("ALTER TABLE `performance_schemes` COMMENT = '员工绩效方案与指标配置表'");
    }

    private function createPerformanceRecords(): void
    {
        Schema::create('performance_records', function (Blueprint $table) {
            $table->id()->comment('绩效记录主键');
            $table->foreignId('user_id')->comment('被考核用户ID')->constrained('users')->cascadeOnDelete();
            $table->foreignId('scheme_id')->comment('绩效方案ID')->constrained('performance_schemes')->restrictOnDelete();
            $table->date('period_month')->index()->comment('考核月份，存当月第一天');
            $table->json('metric_results')->nullable()->comment('各项绩效指标结果JSON');
            $table->decimal('business_score', 7, 2)->default(0)->comment('业务数据得分');
            $table->decimal('manager_score', 7, 2)->default(0)->comment('主管评价得分');
            $table->decimal('final_score', 7, 2)->default(0)->comment('最终绩效得分');
            $table->string('grade', 16)->nullable()->comment('绩效等级');
            $table->string('status', 32)->default('draft')->index()->comment('绩效状态：draft草稿、submitted已提交、reviewing复核中、confirmed已确认、appealing申诉中、closed已归档');
            $table->text('manager_comment')->nullable()->comment('主管评价');
            $table->text('employee_comment')->nullable()->comment('员工确认意见');
            $table->text('appeal_content')->nullable()->comment('员工申诉内容');
            $table->text('appeal_result')->nullable()->comment('申诉处理结果');
            $table->foreignId('reviewed_by')->nullable()->comment('复核用户ID')->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable()->comment('员工确认时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->unique(['user_id', 'period_month'], 'uk_performance_user_month');
        });
        DB::statement("ALTER TABLE `performance_records` COMMENT = '员工月度绩效结果与申诉记录表'");
    }

    private function createAttendanceMonthlies(): void
    {
        Schema::create('attendance_monthlies', function (Blueprint $table) {
            $table->id()->comment('月度考勤主键');
            $table->foreignId('user_id')->comment('员工用户ID')->constrained('users')->cascadeOnDelete();
            $table->date('period_month')->index()->comment('考勤月份，存当月第一天');
            $table->decimal('working_days', 6, 2)->default(0)->comment('应出勤天数');
            $table->decimal('actual_days', 6, 2)->default(0)->comment('实际出勤天数');
            $table->unsignedInteger('late_minutes')->default(0)->comment('迟到分钟数');
            $table->decimal('leave_days', 6, 2)->default(0)->comment('请假天数');
            $table->decimal('absence_days', 6, 2)->default(0)->comment('缺勤天数');
            $table->decimal('overtime_hours', 8, 2)->default(0)->comment('加班小时数');
            $table->decimal('allowance_amount', 18, 2)->default(0)->comment('考勤补贴金额');
            $table->decimal('deduction_amount', 18, 2)->default(0)->comment('考勤扣款金额');
            $table->json('source_data')->nullable()->comment('考勤原始数据JSON');
            $table->string('status', 32)->default('draft')->comment('考勤状态：draft草稿、confirmed已确认、locked已锁定');
            $table->foreignId('confirmed_by')->nullable()->comment('确认用户ID')->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable()->comment('确认时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->unique(['user_id', 'period_month'], 'uk_attendance_user_month');
        });
        DB::statement("ALTER TABLE `attendance_monthlies` COMMENT = '员工月度考勤汇总表'");
    }

    private function createPayrollSheets(): void
    {
        Schema::create('payroll_sheets', function (Blueprint $table) {
            $table->id()->comment('工资表主键');
            $table->string('sheet_no', 64)->unique()->comment('工资表编号');
            $table->date('period_month')->unique()->comment('工资月份，存当月第一天');
            $table->string('formula_version', 32)->comment('工资计算公式版本');
            $table->decimal('total_gross_amount', 18, 2)->default(0)->comment('应发工资总额');
            $table->decimal('total_net_amount', 18, 2)->default(0)->comment('实发工资总额');
            $table->string('status', 32)->default('draft')->index()->comment('工资表状态：draft草稿、hr_review人事复核、finance_review财务复核、approving负责人审批、locked已锁定、paid已发放');
            $table->foreignId('calculated_by')->comment('计算用户ID')->constrained('users')->restrictOnDelete();
            $table->dateTime('calculated_at')->useCurrent()->comment('计算时间');
            $table->foreignId('locked_by')->nullable()->comment('锁定用户ID')->constrained('users')->nullOnDelete();
            $table->dateTime('locked_at')->nullable()->comment('锁定时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
        DB::statement("ALTER TABLE `payroll_sheets` COMMENT = '月度工资汇总与审批主表'");
    }

    private function createPayrollItems(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id()->comment('工资明细主键');
            $table->foreignId('payroll_sheet_id')->comment('工资表ID')->constrained('payroll_sheets')->cascadeOnDelete();
            $table->foreignId('user_id')->comment('员工用户ID')->constrained('users')->restrictOnDelete();
            $table->decimal('base_salary', 18, 2)->default(0)->comment('基本工资');
            $table->decimal('sales_commission', 18, 2)->default(0)->comment('销售净回款提成');
            $table->decimal('performance_bonus', 18, 2)->default(0)->comment('绩效奖金');
            $table->decimal('project_bonus', 18, 2)->default(0)->comment('项目奖金');
            $table->decimal('management_bonus', 18, 2)->default(0)->comment('管理绩效奖金');
            $table->decimal('allowance_amount', 18, 2)->default(0)->comment('补贴金额');
            $table->decimal('refund_clawback', 18, 2)->default(0)->comment('退款提成冲回金额');
            $table->decimal('attendance_deduction', 18, 2)->default(0)->comment('考勤扣款金额');
            $table->decimal('social_security', 18, 2)->default(0)->comment('个人社保扣款');
            $table->decimal('housing_fund', 18, 2)->default(0)->comment('个人公积金扣款');
            $table->decimal('personal_tax', 18, 2)->default(0)->comment('个人所得税');
            $table->decimal('gross_salary', 18, 2)->default(0)->comment('应发工资');
            $table->decimal('net_salary', 18, 2)->default(0)->comment('实发工资');
            $table->json('calculation_detail')->nullable()->comment('逐项计算来源与过程JSON');
            $table->string('status', 32)->default('pending')->index()->comment('明细状态：pending待确认、confirmed已确认、appealing申诉中、paid已发放');
            $table->text('appeal_content')->nullable()->comment('工资申诉内容');
            $table->text('appeal_result')->nullable()->comment('工资申诉处理结果');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->unique(['payroll_sheet_id', 'user_id'], 'uk_payroll_sheet_user');
        });
        DB::statement("ALTER TABLE `payroll_items` COMMENT = '员工工资逐项计算与确认明细表'");
    }

    private function createCrmCallRecords(): void
    {
        Schema::create('crm_call_records', function (Blueprint $table) {
            $table->id()->comment('通话记录主键');
            $table->string('provider_call_id', 128)->unique()->comment('呼叫供应商通话唯一ID');
            $table->foreignId('employee_id')->comment('拨打员工用户ID')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->comment('匹配客户ID')->constrained('crm_customers')->nullOnDelete();
            $table->string('customer_phone', 64)->comment('客户号码密文或受控原文');
            $table->string('customer_phone_masked', 32)->comment('客户号码脱敏展示值');
            $table->dateTime('started_at')->index()->comment('通话开始时间');
            $table->unsignedInteger('duration_seconds')->default(0)->comment('通话时长秒数');
            $table->string('call_result', 32)->index()->comment('通话结果：connected接通、rejected拒接、no_answer无人接听、busy忙线、invalid无效号码');
            $table->string('recording_url', 1000)->nullable()->comment('授权后的录音地址');
            $table->boolean('is_valid_communication')->default(false)->comment('是否有效沟通');
            $table->boolean('demand_activated')->default(false)->comment('是否激活客户需求');
            $table->boolean('diagnosis_booked')->default(false)->comment('是否预约诊断会');
            $table->string('quality_label', 64)->nullable()->comment('通话质检标签');
            $table->string('compliance_status', 32)->default('unchecked')->comment('合规状态：unchecked未检查、passed通过、warning预警、violation违规');
            $table->dateTime('next_follow_at')->nullable()->index()->comment('下次跟进时间');
            $table->json('raw_payload')->nullable()->comment('供应商原始回调数据JSON');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->index(['employee_id', 'started_at'], 'idx_call_employee_time');
        });
        DB::statement("ALTER TABLE `crm_call_records` COMMENT = '电销CRM员工通话与质检记录表'");
    }

    private function createApprovalInstances(): void
    {
        Schema::create('approval_instances', function (Blueprint $table) {
            $table->id()->comment('审批实例主键');
            $table->string('approval_no', 64)->unique()->comment('审批单号');
            $table->string('business_type', 64)->index()->comment('业务类型：order订单、refund退款、payroll工资、performance绩效等');
            $table->unsignedBigInteger('business_id')->index()->comment('业务记录ID');
            $table->string('approval_type', 64)->comment('审批类型');
            $table->string('title', 255)->comment('审批标题');
            $table->string('status', 32)->default('pending')->index()->comment('审批状态：pending审批中、approved已通过、rejected已驳回、cancelled已撤销');
            $table->unsignedSmallInteger('current_step')->default(1)->comment('当前审批步骤');
            $table->foreignId('applicant_id')->comment('申请用户ID')->constrained('users')->restrictOnDelete();
            $table->dateTime('submitted_at')->useCurrent()->comment('提交时间');
            $table->dateTime('finished_at')->nullable()->comment('审批结束时间');
            $table->json('snapshot_data')->nullable()->comment('提交时业务数据快照JSON');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->index(['business_type', 'business_id'], 'idx_approval_business');
        });
        DB::statement("ALTER TABLE `approval_instances` COMMENT = '跨模块通用审批实例表'");
    }

    private function createApprovalRecords(): void
    {
        Schema::create('approval_records', function (Blueprint $table) {
            $table->id()->comment('审批记录主键');
            $table->foreignId('approval_instance_id')->comment('审批实例ID')->constrained('approval_instances')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_no')->comment('审批步骤序号');
            $table->string('step_name', 128)->comment('审批步骤名称');
            $table->foreignId('approver_id')->comment('审批用户ID')->constrained('users')->restrictOnDelete();
            $table->string('action', 32)->default('pending')->comment('审批动作：pending待处理、approved同意、rejected驳回、returned退回、transferred转交');
            $table->string('comment', 1000)->nullable()->comment('审批意见');
            $table->dateTime('acted_at')->nullable()->comment('审批操作时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->index(['approver_id', 'action'], 'idx_approval_record_approver_action');
        });
        DB::statement("ALTER TABLE `approval_records` COMMENT = '通用审批节点处理记录表'");
    }

    private function createAttachments(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id()->comment('附件主键');
            $table->string('business_type', 64)->index()->comment('所属业务类型');
            $table->unsignedBigInteger('business_id')->index()->comment('所属业务记录ID');
            $table->string('attachment_type', 64)->nullable()->comment('附件类型：contract合同、payment付款、license营业执照、authorization授权、evidence凭证等');
            $table->string('original_name', 255)->comment('附件原始文件名');
            $table->string('storage_disk', 32)->default('public')->comment('文件存储磁盘');
            $table->string('storage_path', 1000)->comment('文件存储路径');
            $table->string('mime_type', 128)->nullable()->comment('文件MIME类型');
            $table->unsignedBigInteger('file_size')->default(0)->comment('文件大小字节数');
            $table->string('file_hash', 128)->nullable()->index()->comment('文件哈希值');
            $table->unsignedInteger('version_no')->default(1)->comment('附件版本号');
            $table->foreignId('uploaded_by')->comment('上传用户ID')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->softDeletes()->comment('逻辑删除时间');
            $table->index(['business_type', 'business_id'], 'idx_attachment_business');
        });
        DB::statement("ALTER TABLE `attachments` COMMENT = '跨模块通用附件与版本表'");
    }

    private function createReminders(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id()->comment('提醒主键');
            $table->foreignId('user_id')->comment('接收提醒用户ID')->constrained('users')->cascadeOnDelete();
            $table->string('business_type', 64)->nullable()->index()->comment('关联业务类型');
            $table->unsignedBigInteger('business_id')->nullable()->index()->comment('关联业务记录ID');
            $table->string('reminder_type', 64)->index()->comment('提醒类型：deadline截止、overdue逾期、probation试用期、renewal续费、approval审批等');
            $table->string('title', 255)->comment('提醒标题');
            $table->string('content', 1000)->nullable()->comment('提醒内容');
            $table->dateTime('remind_at')->index()->comment('计划提醒时间');
            $table->string('level', 16)->default('normal')->comment('提醒级别：normal普通、warning预警、urgent紧急');
            $table->string('status', 16)->default('pending')->index()->comment('提醒状态：pending待发送、sent已发送、read已读、cancelled已取消');
            $table->dateTime('sent_at')->nullable()->comment('实际发送时间');
            $table->dateTime('read_at')->nullable()->comment('用户读取时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
        DB::statement("ALTER TABLE `reminders` COMMENT = '跨模块待办、超时与预警提醒表'");
    }

    private function createOperationLogs(): void
    {
        Schema::create('operation_logs', function (Blueprint $table) {
            $table->id()->comment('操作日志主键');
            $table->foreignId('user_id')->nullable()->comment('操作用户ID')->constrained('users')->nullOnDelete();
            $table->string('module', 64)->index()->comment('业务模块');
            $table->string('business_type', 64)->nullable()->index()->comment('业务类型');
            $table->unsignedBigInteger('business_id')->nullable()->index()->comment('业务记录ID');
            $table->string('action', 64)->comment('操作动作');
            $table->string('description', 500)->nullable()->comment('操作说明');
            $table->json('before_data')->nullable()->comment('变更前数据JSON');
            $table->json('after_data')->nullable()->comment('变更后数据JSON');
            $table->string('ip_address', 64)->nullable()->comment('操作IP地址');
            $table->string('user_agent', 1000)->nullable()->comment('客户端标识');
            $table->dateTime('operated_at')->useCurrent()->index()->comment('操作时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->index(['module', 'business_type', 'business_id'], 'idx_operation_business');
        });
        DB::statement("ALTER TABLE `operation_logs` COMMENT = '跨模块关键数据变更审计日志表'");
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_logs');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('approval_records');
        Schema::dropIfExists('approval_instances');
        Schema::dropIfExists('crm_call_records');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_sheets');
        Schema::dropIfExists('attendance_monthlies');
        Schema::dropIfExists('performance_records');
        Schema::dropIfExists('performance_schemes');
        Schema::dropIfExists('probation_reviews');
        Schema::dropIfExists('crm_followups');
    }
};
