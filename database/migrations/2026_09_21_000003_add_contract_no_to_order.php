<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order', function (Blueprint $table) {
            $table->string('contract_no', 64)->nullable()->unique()->after('product_name')->comment('合同编号');
        });
    }

    public function down(): void
    {
        Schema::table('order', function (Blueprint $table) {
            $table->dropUnique('order_contract_no_unique');
            $table->dropColumn('contract_no');
        });
    }
};
