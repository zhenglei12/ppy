<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('crm_weiwenjia_users')) {
            Schema::create('crm_weiwenjia_users', function (Blueprint $table) {
                $table->id();
                $table->string('external_id', 128)->unique()->comment('微问家用户ID，不关联本地users');
                $table->string('name', 128)->default('')->comment('第三方成员姓名');
                $table->string('department_name', 128)->nullable()->comment('第三方部门');
                $table->string('status', 32)->nullable()->comment('第三方成员状态');
                $table->json('raw_payload')->nullable()->comment('第三方原始数据');
                $table->dateTime('external_updated_at')->nullable();
                $table->dateTime('synced_at')->nullable();
                $table->timestamps();
                $table->index(['department_name', 'name']);
            });
        }
        if (! Schema::hasTable('crm_weiwenjia_daily_stats')) {
            Schema::create('crm_weiwenjia_daily_stats', function (Blueprint $table) {
                $table->id();
                $table->date('report_date')->comment('日报日期');
                $table->string('external_user_id', 128)->comment('微问家成员ID');
                $table->string('user_name', 128)->default('')->comment('成员姓名快照');
                $table->string('department_name', 128)->nullable()->comment('部门快照');
                $table->unsignedInteger('effective_calls')->default(0)->comment('有效接通');
                $table->unsignedInteger('effective_communications')->default(0)->comment('有效沟通数');
                $table->unsignedInteger('wechat_adds')->default(0)->comment('加V');
                $table->unsignedInteger('effective_dialogues')->default(0)->comment('有效对话');
                $table->unsignedInteger('effective_activations')->default(0)->comment('有效激活');
                $table->unsignedInteger('daily_moments')->default(0)->comment('日朋友圈数');
                $table->unsignedInteger('ai_reports')->default(0)->comment('AI监测报告');
                $table->unsignedInteger('appointments')->default(0)->comment('预约');
                $table->string('appointment_progress', 64)->nullable()->comment('预约进度');
                $table->unsignedInteger('accompany_visits')->default(0)->comment('陪访');
                $table->unsignedInteger('today_deals')->default(0)->comment('今日成交');
                $table->json('raw_payload')->nullable()->comment('日报原始数据');
                $table->timestamps();
                $table->unique(['report_date', 'external_user_id'], 'uk_weiwenjia_daily_user');
                $table->index(['report_date', 'department_name']);
            });
        }
        if (! Schema::hasTable('crm_weiwenjia_call_records')) {
            Schema::create('crm_weiwenjia_call_records', function (Blueprint $table) {
                $table->id();
                $table->string('external_id', 128)->unique()->comment('第三方通话记录ID');
                $table->string('external_user_id', 128)->nullable();
                $table->string('external_customer_id', 128)->nullable();
                $table->string('phone', 64)->nullable();
                $table->boolean('through')->default(false)->comment('是否接通');
                $table->unsignedInteger('duration')->default(0)->comment('通话时长秒');
                $table->string('tip_type', 64)->nullable();
                $table->string('tip_name', 128)->nullable();
                $table->dateTime('called_at')->nullable()->index();
                $table->json('raw_payload')->nullable();
                $table->timestamps();
                $table->index(['external_user_id', 'called_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_weiwenjia_call_records');
        Schema::dropIfExists('crm_weiwenjia_daily_stats');
        Schema::dropIfExists('crm_weiwenjia_users');
    }
};
