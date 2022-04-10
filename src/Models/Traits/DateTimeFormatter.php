<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 */

declare(strict_types=1);

namespace Larva\Pay\Models\Traits;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * 默认日期格式
 * @mixin Model
 * @author Tongle Xu <xutongle@gmail.com>
 */
trait DateTimeFormatter
{
    /**
     * 为数组 / JSON 序列化准备日期。
     *
     * @param DateTimeInterface $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format($this->getDateFormat());
    }
}
