<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order', function (Blueprint $table) {
            $table->string("receipt_time")->nullable()->comment("付款时间");
            $table->string("receipt_account")->nullable()->comment("收款账户");
            $table->string('classify_id')->nullable()->comment('分类id');
            $table->string('alter_word')->nullable()->comment('编辑上传的字数');
            $table->index("classify_id");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order', function (Blueprint $table) {
            //
        });
    }
}
