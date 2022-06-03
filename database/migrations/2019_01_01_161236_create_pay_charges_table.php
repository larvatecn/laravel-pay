<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pay_charges', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment('收款流水号');
            $table->string('trade_channel', 64)->nullable()->comment('付款渠道');
            $table->string('trade_type', 16)->nullable()->comment('交易类型');
            $table->string('transaction_no', 64)->nullable()->comment('支付渠道流水号');
            $table->morphs('order');//订单关联
            $table->string('subject', 256)->nullable()->comment('订单标题');
            $table->string('description', 127)->nullable()->comment('商品描述');
            $table->unsignedInteger('total_amount')->comment('订单总金额');
            $table->unsignedInteger('refunded_amount')->nullable()->default(0)->comment('已退款钱数');
            $table->string('currency', 3)->default('CNY')->comment('货币类型');
            $table->string('state', 32)->nullable()->comment('交易状态');
            $table->ipAddress('client_ip')->nullable()->comment('用户的客户端IP');
            $table->json('metadata')->nullable()->comment('元信息');
            $table->json('credential')->nullable()->comment('客户端支付凭证');
            $table->json('extra')->nullable()->comment('成功时额外返回的渠道信息');
            $table->json('failure')->nullable()->comment('错误信息');
            $table->timestamp('expired_at')->nullable()->comment('订单失效时间');
            $table->timestamp('succeed_at')->nullable()->comment('订单支付完成时间');//银联支付成功时间为接收异步通知的时间
            $table->softDeletes();
            $table->timestamps();

            $table->comment('收款记录表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pay_charges');
    }
};
