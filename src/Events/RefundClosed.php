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
 * 退款关闭事件
 * @author Tongle Xu <xutongle@gmail.com>
 */
class RefundClosed
{
    use SerializesModels;

    /**
     * @var Refund
     */
    public Refund $refund;

    /**
     * RefundFailure constructor.
     * @param Refund $refund
     */
    public function __construct(Refund $refund)
    {
        $this->refund = $refund;
    }
}
