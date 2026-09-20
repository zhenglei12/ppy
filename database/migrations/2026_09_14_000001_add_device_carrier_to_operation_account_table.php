<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_account', function (Blueprint $table) {
            $table->string('device_carrier')->nullable()->after('platform_account_id')->comment('设备载体');
        });
    }

    public function down(): void
    {
        Schema::table('operation_account', function (Blueprint $table) {
            $table->dropColumn('device_carrier');
        });
    }
};
