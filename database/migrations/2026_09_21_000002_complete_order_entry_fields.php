<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order', function (Blueprint $table) {
            $table->string('payment_method', 32)->nullable()->after('payment_terms')->comment('付款方式：bank银行转账、wechat微信、alipay支付宝、cash现金、other其他');
            $table->string('payment_subject', 255)->nullable()->after('payment_method')->comment('付款主体名称');
            $table->date('payment_due_date')->nullable()->after('payment_subject')->comment('约定付款日期');
            $table->string('company_account', 255)->nullable()->after('payment_due_date')->comment('收款公司账户');
            $table->boolean('invoice_required')->default(false)->after('company_account')->comment('是否需要开具发票');
            $table->string('invoice_type', 32)->nullable()->after('invoice_required')->comment('发票类型：normal普票、special专票、electronic电子票');
            $table->string('invoice_title', 255)->nullable()->after('invoice_type')->comment('发票抬头');
            $table->string('invoice_tax_no', 64)->nullable()->after('invoice_title')->comment('发票税号');
            $table->string('invoice_status', 32)->default('not_applied')->after('invoice_tax_no')->comment('开票状态：not_applied未申请、applied已申请、issued已开具');

            $table->text('success_criteria')->nullable()->after('service_objective')->comment('90天成功标准');
            $table->json('baseline_data')->nullable()->after('success_criteria')->comment('服务启动基线数据JSON');
            $table->json('required_materials')->nullable()->after('baseline_data')->comment('客户需配合资料清单JSON');
            $table->date('materials_due_date')->nullable()->after('required_materials')->comment('客户资料最晚提交日期');
            $table->dateTime('kickoff_meeting_at')->nullable()->after('materials_due_date')->comment('首次启动会时间');
            $table->json('kickoff_attendees')->nullable()->after('kickoff_meeting_at')->comment('首次启动会参会人JSON');

            $table->boolean('ranking_commitment')->default(false)->after('sales_commitment')->comment('是否承诺排名');
            $table->boolean('acquisition_commitment')->default(false)->after('ranking_commitment')->comment('是否承诺获客或成交');
            $table->string('qualification_status', 32)->default('pending')->after('acquisition_commitment')->comment('资质核验状态：pending待核验、verified已核验、missing缺失');
            $table->boolean('case_authorized')->default(false)->after('qualification_status')->comment('是否取得案例展示授权');
            $table->boolean('logo_authorized')->default(false)->after('case_authorized')->comment('是否取得Logo使用授权');
            $table->boolean('portrait_authorized')->default(false)->after('logo_authorized')->comment('是否取得肖像使用授权');
            $table->text('refund_terms')->nullable()->after('portrait_authorized')->comment('退款条款');
            $table->text('special_delivery_terms')->nullable()->after('refund_terms')->comment('特殊交付约定');
            $table->text('complaint_history')->nullable()->after('special_delivery_terms')->comment('客户历史客诉或争议');
            $table->text('pending_verification')->nullable()->after('complaint_history')->comment('待核实事实与责任人');

            $table->json('contract_files')->nullable()->after('pending_verification')->comment('合同或订单确认书第三方文件地址JSON数组');
            $table->json('payment_voucher_files')->nullable()->after('contract_files')->comment('付款凭证第三方文件地址JSON数组');
            $table->json('license_files')->nullable()->after('payment_voucher_files')->comment('营业执照第三方文件地址JSON数组');
            $table->json('authorization_files')->nullable()->after('license_files')->comment('客户授权文件第三方地址JSON数组');
            $table->json('sales_handover_files')->nullable()->after('authorization_files')->comment('销售交付清单第三方文件地址JSON数组');
            $table->json('approval_files')->nullable()->after('sales_handover_files')->comment('报价与特殊条款审批文件地址JSON数组');

            $table->index('payment_due_date', 'idx_order_payment_due_date');
            $table->index('materials_due_date', 'idx_order_materials_due_date');
        });
    }

    public function down(): void
    {
        Schema::table('order', function (Blueprint $table) {
            $table->dropIndex('idx_order_payment_due_date');
            $table->dropIndex('idx_order_materials_due_date');
            $table->dropColumn([
                'payment_method', 'payment_subject', 'payment_due_date', 'company_account', 'invoice_required',
                'invoice_type', 'invoice_title', 'invoice_tax_no', 'invoice_status', 'success_criteria',
                'baseline_data', 'required_materials', 'materials_due_date', 'kickoff_meeting_at', 'kickoff_attendees',
                'ranking_commitment', 'acquisition_commitment', 'qualification_status', 'case_authorized',
                'logo_authorized', 'portrait_authorized', 'refund_terms', 'special_delivery_terms',
                'complaint_history', 'pending_verification',
                'contract_files', 'payment_voucher_files', 'license_files', 'authorization_files',
                'sales_handover_files', 'approval_files',
            ]);
        });
    }
};
