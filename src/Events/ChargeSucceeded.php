<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 */

declare(strict_types=1);

namespace Larva\Pay\Events;

use Illuminate\Queue\SerializesModels;
use Larva\Pay\Models\Charge;

/**
 * 交易已支付
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class ChargeSucceeded
{
    use SerializesModels;

    /**
     * @var Charge
     */
    public Charge $charge;

    /**
     * ChargeShipped constructor.
     * @param Charge $charge
     */
    public function __construct(Charge $charge)
    {
        $this->charge = $charge;
    }
}
