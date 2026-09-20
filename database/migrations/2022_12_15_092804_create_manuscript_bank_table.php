<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateManuscriptBankTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('manuscript_bank', function (Blueprint $table) {
            $table->id();
            $table->string('subject')->index('subject')->nullable()->comment("题目");
            $table->string('word_number')->index('word_number')->nullable()->comment('字数');
            $table->string('manuscript')->nullable()->comment('稿件下载');
            $table->string('classify_id')->nullable()->comment('分类id');
            $table->string('classify_local_id')->nullable()->comment('分类id');
            $table->index("classify_local_id");
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
        Schema::dropIfExists('manuscript_bank');
    }
}
