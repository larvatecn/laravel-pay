<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pay_refunds', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment('退款流水号');
            $table->unsignedBigInteger('charge_id')->comment('付款流水号');
            $table->string('transaction_no', 64)->nullable()->comment('网关流水号');
            $table->unsignedInteger('amount')->comment('退款金额');
            $table->string('reason', 127)->nullable()->comment('退款原因');
            $table->string('status')->comment('退款状态');
            $table->json('failure')->nullable()->comment('错误信息');
            $table->json('extra')->nullable()->comment('退款成功时额外返回的渠道信息');
            $table->timestamp('succeed_at')->nullable()->comment('成功时间');
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('charge_id')->references('id')->on('pay_charges');

            $table->comment('退款记录表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pay_refunds');
    }
};
