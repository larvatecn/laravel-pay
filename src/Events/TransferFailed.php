<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 */

declare(strict_types=1);

namespace Larva\Pay\Events;

use Illuminate\Queue\SerializesModels;
use Larva\Pay\Models\Transfer;

/**
 * 企业付款失败事件
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class TransferFailed
{
    use SerializesModels;

    /**
     * @var Transfer
     */
    public Transfer $transfer;

    /**
     * TransferShipped constructor.
     * @param Transfer $transfer
     */
    public function __construct(Transfer $transfer)
    {
        $this->transfer = $transfer;
    }
}
