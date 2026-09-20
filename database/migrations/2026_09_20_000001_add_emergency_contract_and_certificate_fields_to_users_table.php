<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'emergency_contact_name')) {
                $table->string('emergency_contact_name', 64)->nullable()->comment('紧急联系人姓名');
            }
            if (! Schema::hasColumn('users', 'emergency_contact_phone')) {
                $table->string('emergency_contact_phone', 32)->nullable()->comment('紧急联系人电话');
            }
            if (! Schema::hasColumn('users', 'contract_start_date')) {
                $table->date('contract_start_date')->nullable()->comment('劳动合同开始日期');
            }
            if (! Schema::hasColumn('users', 'contract_end_date')) {
                $table->date('contract_end_date')->nullable()->index()->comment('劳动合同结束日期');
            }
            if (! Schema::hasColumn('users', 'certificate_images')) {
                $table->json('certificate_images')->nullable()->comment('证件资料图片地址JSON数组');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'emergency_contact_name',
                'emergency_contact_phone',
                'contract_start_date',
                'contract_end_date',
                'certificate_images',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
