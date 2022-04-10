<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 */

declare(strict_types=1);

namespace Larva\Pay\Events;

use Illuminate\Queue\SerializesModels;
use Larva\Pay\Models\Refund;

/**
 * 退款成功事件
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class RefundSucceeded
{
    use SerializesModels;

    /**
     * @var Refund
     */
    public $refund;

    /**
     * RefundSuccess constructor.
     * @param Refund $refund
     */
    public function __construct(Refund $refund)
    {
        $this->refund = $refund;
    }
}
