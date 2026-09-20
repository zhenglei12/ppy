<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device', function (Blueprint $table) {
            $table->id();
            $table->string('device_number')->unique()->comment('设备编号');
            $table->string('model')->nullable()->comment('型号');
            $table->string('color')->nullable()->comment('颜色');
            $table->string('memory')->nullable()->comment('内存');
            $table->date('purchase_date')->nullable()->comment('购买日期');
            $table->string('purchase_channel')->nullable()->comment('购买渠道');
            $table->decimal('price', 10, 2)->nullable()->comment('价格');
            $table->string('holder')->nullable()->comment('持有人');
            $table->enum('status', ['in_use', 'pending', 'repairing', 'outbound'])
                ->default('pending')
                ->comment('状态：in_use使用，pending待定，repairing维修，outbound出库');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device');
    }
};
