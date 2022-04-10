<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 */

declare(strict_types=1);

namespace Larva\Pay\Models\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * 使用时间作为主键
 * @mixin Model
 */
trait UsingDatetimeAsPrimaryKey
{
    public static function bootUsingDatetimeAsPrimaryKey(): void
    {
        static::creating(function (self $model): void {
            /* @var Model|UsingDatetimeAsPrimaryKey $model */
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = $model->generateKey();
            }
        });
    }

    /**
     * 生成主键 长度16位
     * @return int
     */
    public function generateKey(): int
    {
        $i = rand(0, 99);
        do {
            if (99 == $i) {
                $i = 0;
            }
            $i++;
            $id = date('YmdHis') . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            $row = static::query()->where($this->primaryKey, '=', $id)->exists();
        } while ($row);
        return (int)$id;
    }

    /**
     * 关闭主键自增
     * @return bool
     */
    public function getIncrementing(): bool
    {
        return false;
    }
}
