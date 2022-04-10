<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 */

declare(strict_types=1);

namespace Larva\Transaction\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Larva\Pay\Models\Charge;

/**
 * 检查付款单是否过期
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class CheckChargeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 任务可以尝试的最大次数。
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * @var Charge
     */
    protected Charge $charge;

    /**
     * Create a new job instance.
     *
     * @param Charge $charge
     */
    public function __construct(Charge $charge)
    {
        $this->charge = $charge;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        if ($this->charge->state == Charge::STATE_NOTPAY) {
            if ($this->charge->expired_at && $this->charge->expired_at->diffInSeconds(Carbon::now()) < 0) {
                $this->charge->close();//关闭订单
            } else {
                $this->release(now()->addMinutes(2));
            }
        }
    }
}
