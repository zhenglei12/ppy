<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order', function (Blueprint $table) {
            $table->id();
            $table->integer('task_type')->index('task_type')->comment("任务类型1 论文写作, 2期刊发表,3ppt,4翻译,5其他");
            $table->string('subject')->index('subject')->comment("题目");
            $table->string('word_number')->index('word_number')->comment('字数');
            $table->string('task_ask')->comment('任务要求');
            $table->string('name')->index('name')->comment('客户名称');
            $table->string('phone')->nullable()->comment('客户电话');
            $table->text('wr_where')->nullable()->comment('写作要求');
            $table->string('want_name')->nullable()->comment('旺旺名');
            $table->string('submission_time')->nullable()->comment('截止时间');
            $table->decimal("amount", 10,4)->default(0)->comment("订单总额");
            $table->decimal("received_amount", 10,4)->default(0)->comment("已收金额");
            $table->string('pay_img')->nullable()->comment('付款截图');
            $table->integer('pay_type')->nullable()->comment('支付方式1 支付宝 2微信，3银行转账,4对公账户,5线上支付');
            $table->string('detail_re')->nullable()->comment('详细要求');
            $table->string('staff_name')->comment('客服名称');
            $table->string('edit_name')->nullable()->comment('编辑名称');
            $table->string('remark')->nullable()->comment('备注');
            $table->string('manuscript')->nullable()->comment('稿件下载');
            $table->string('status')->default(-1)->index('status')->comment('状态 -1等待安排 1 写作中， 2打回修改， 3 订单完成，4提交客户, 5已经交稿');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order');
    }
}
