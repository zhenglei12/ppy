<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $columns = [
            'source' => fn (Blueprint $t) => $t->string('source', 32)->default('local')->index()->comment('客户来源：local本地、weiwenjia微问家'),
            'external_id' => fn (Blueprint $t) => $t->string('external_id', 64)->nullable()->comment('第三方客户ID'),
            'external_status' => fn (Blueprint $t) => $t->string('external_status', 64)->nullable()->comment('第三方客户状态'),
            'external_status_name' => fn (Blueprint $t) => $t->string('external_status_name', 128)->nullable()->comment('第三方客户状态名称'),
            'external_owner_name' => fn (Blueprint $t) => $t->string('external_owner_name', 128)->nullable()->comment('第三方负责人名称'),
            'external_labels' => fn (Blueprint $t) => $t->json('external_labels')->nullable()->comment('第三方客户标签'),
            'external_payload' => fn (Blueprint $t) => $t->json('external_payload')->nullable()->comment('第三方原始数据'),
            'external_created_at' => fn (Blueprint $t) => $t->dateTime('external_created_at')->nullable()->comment('第三方创建时间'),
            'external_updated_at' => fn (Blueprint $t) => $t->dateTime('external_updated_at')->nullable()->comment('第三方更新时间'),
            'synced_at' => fn (Blueprint $t) => $t->dateTime('synced_at')->nullable()->comment('最后同步时间'),
        ];
        foreach ($columns as $name => $definition) {
            if (! Schema::hasColumn('crm_customers', $name)) {
                Schema::table('crm_customers', $definition);
            }
        }
        try {
            Schema::table('crm_customers', fn (Blueprint $t) => $t->unique(['source', 'external_id'], 'uk_crm_customer_source_external'));
        } catch (\Throwable $e) {
            // 已存在索引时保持幂等，避免重复执行迁移失败。
        }
    }

    public function down(): void
    {
        Schema::table('crm_customers', function (Blueprint $table) {
            $table->dropUnique('uk_crm_customer_source_external');
            foreach (['source', 'external_id', 'external_status', 'external_status_name', 'external_owner_name', 'external_labels', 'external_payload', 'external_created_at', 'external_updated_at', 'synced_at'] as $column) {
                $table->dropColumn($column);
            }
        });
    }
};
