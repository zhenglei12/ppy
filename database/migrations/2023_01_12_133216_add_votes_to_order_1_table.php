<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVotesToOrder1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order', function (Blueprint $table) {
            $table->integer('finance_check')->default(-1)->comment("财务是否审核");
            $table->string("edit_submit_time")->nullable()->comment("编辑提交时间");
            $table->string("after_banlace")->nullable()->comment("售后金额");
            $table->string("after_time")->nullable()->comment("售后时间");
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
