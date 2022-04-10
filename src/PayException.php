<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 */

declare(strict_types=1);

namespace Larva\Pay;

use Throwable;

/**
 * 异常
 * @author Tongle Xu <xutongle@gmail.com>
 */
class PayException extends \RuntimeException
{
    public function __construct($message = '', $code = 500, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
