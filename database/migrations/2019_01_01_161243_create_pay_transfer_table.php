<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Larva\Pay\Models\Transfer;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('pay_transfer', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment('付款流水号');
            $table->string('trade_channel', 64)->comment('支付渠道');
            $table->string('transaction_no', 64)->nullable()->comment('网关流水号');
            $table->string('status', 15)->default(Transfer::STATUS_PENDING)->nullable()->comment('状态');
            $table->morphs('order');//付款单关联
            $table->unsignedInteger('amount')->comment('金额');
            $table->string('currency', 3)->comment('货币代码');
            $table->string('description')->nullable()->comment('备注信息');
            $table->json('failure')->nullable()->comment('错误信息描述');
            $table->json('recipient')->nullable()->comment('收款人信息');//元数据
            $table->json('extra')->nullable()->comment('网关返回的信息');//附加参数
            $table->timestamp('succeed_at')->nullable()->comment('成功时间');
            $table->softDeletes();
            $table->timestamps();

            $table->comment('付款记录表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('pay_transfer');
    }
};