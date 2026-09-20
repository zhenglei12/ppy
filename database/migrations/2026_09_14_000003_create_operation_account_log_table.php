<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_account_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_account_id')->comment('运营账号ID')->constrained('operation_account')->cascadeOnDelete();
            $table->text('content')->comment('修改内容');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_account_log');
    }
};
