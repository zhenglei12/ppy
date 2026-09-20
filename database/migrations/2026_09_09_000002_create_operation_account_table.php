<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_account', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->comment('运营平台');
            $table->string('name')->comment('名称');
            $table->string('platform_account_id')->comment('平台账号ID');
            $table->string('bound_phone_card')->nullable()->comment('绑定电话卡');
            $table->string('phone_card_owner')->nullable()->comment('电话卡主人');
            $table->text('other_information')->nullable()->comment('其他信息');
            $table->string('person_in_charge')->nullable()->comment('负责人');
            $table->enum('status', ['enabled', 'disabled', 'frozen'])
                ->default('enabled')
                ->comment('状态：enabled启用，disabled停用，frozen冻结');
            $table->timestamps();

            $table->unique(['platform', 'platform_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_account');
    }
};
