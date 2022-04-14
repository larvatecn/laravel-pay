<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 */

declare(strict_types=1);

namespace Larva\Pay\Models;

use Carbon\CarbonInterface;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Event;
use Larva\Pay\Casts\Failure;
use Larva\Pay\Events\TransferFailed;
use Larva\Pay\Events\TransferSucceeded;
use Larva\Pay\Jobs\HandleTransferJob;
use Larva\Pay\Pay;

/**
 * 企业付款模型，处理提现
 *
 * @property int $id 付款单ID
 * @property string $trade_channel 付款渠道
 * @property string $status 状态
 * @property int $amount 金额
 * @property string $currency 币种
 * @property string $description 描述
 * @property string $transaction_no 网关交易号
 * @property array $failure 失败信息
 * @property array $recipient 接收者
 * @property array $extra 扩展数据
 * @property CarbonInterface|null $succeed_at 成功时间
 * @property CarbonInterface|null $deleted_at 删除时间
 * @property CarbonInterface $created_at 创建时间
 * @property CarbonInterface $updated_at 更新时间
 *
 * @property-read boolean $succeed
 *
 * @property Model $order
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class Transfer extends Model
{
    use SoftDeletes, Traits\DateTimeFormatter, Traits\UsingDatetimeAsPrimaryKey;

    //付款状态
    public const STATUS_PENDING = 'PENDING';//待处理
    public const STATUS_SUCCESS = 'SUCCESS';//成功
    public const STATUS_ABNORMAL = 'ABNORMAL';//异常

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pay_transfer';

    /**
     * @var bool 关闭主键自增
     */
    public $incrementing = false;

    /**
     * @var array 批量赋值属性
     */
    public $fillable = [
        'id', 'trade_channel', 'status', 'amount', 'currency', 'description', 'transaction_no',
        'failure', 'recipient', 'extra', 'succeed_at'
    ];

    /**
     * 这个属性应该被转换为原生类型.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'int',
        'trade_channel' => 'string',
        'status' => 'string',
        'amount' => 'int',
        'currency' => 'string',
        'description' => 'string',
        'transaction_no' => 'string',
        'recipient' => 'array',
        'extra' => 'array',
        'failure' => Failure::class,
    ];

    /**
     * 应该被调整为日期的属性
     *
     * @var array
     */
    protected $dates = [
        'succeed_at', 'created_at', 'updated_at', 'deleted_at',
    ];

    /**
     * 交易状态，枚举值
     * @var array|string[]
     */
    protected static array $statusMaps = [
        self::STATUS_PENDING => '待处理',
        self::STATUS_SUCCESS => '付款成功',
        self::STATUS_ABNORMAL => '付款异常',
    ];

    /**
     * 交易状态，枚举值
     * @var array|string[]
     */
    protected static array $statusDots = [
        self::STATUS_PENDING => 'info',
        self::STATUS_SUCCESS => 'success',
        self::STATUS_ABNORMAL => 'error',
    ];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    public static function booted()
    {
        static::creating(function (Transfer $model) {
            $model->id = $model->generateKey();
            $model->currency = $model->currency ?: 'CNY';
            $model->status = static::STATUS_PENDING;
        });
        static::created(function (Transfer $model) {
            HandleTransferJob::dispatch($model)->delay(1);
        });
    }

    /**
     * 获取 Status Label
     * @return string[]
     */
    public static function getStatusMaps(): array
    {
        return static::$statusMaps;
    }

    /**
     * 获取状态Dot
     * @return string[]
     */
    public static function getStateDots(): array
    {
        return static::$statusDots;
    }

    /**
     * 多态关联
     * @return MorphTo
     */
    public function order(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 是否已付款
     * @return bool
     */
    public function getSucceedAttribute(): bool
    {
        return $this->status == self::STATUS_SUCCESS;
    }

    /**
     * 设置已付款
     * @param string $transactionNo
     * @param array $extra
     * @return bool
     */
    public function markSucceeded(string $transactionNo, array $extra = []): bool
    {
        if ($this->succeed) {
            return true;
        }
        $state = $this->update([
            'transaction_no' => $transactionNo,
            'transferred_at' => $this->freshTimestamp(),
            'status' => static::STATUS_SUCCESS,
            'extra' => $extra
        ]);
        Event::dispatch(new TransferSucceeded($this));
        return $state;
    }

    /**
     * 设置提现错误
     * @param string|int $code
     * @param string $desc
     * @param array $extra
     * @return bool
     */
    public function markFailed($code, string $desc, array $extra = []): bool
    {
        $res = $this->update([
            'status' => self::STATUS_ABNORMAL,
            'failure' => ['code' => $code, 'desc' => $desc],
            'extra' => $extra
        ]);
        Event::dispatch(new TransferFailed($this));
        return $res;
    }

    /**
     * 主动发送付款请求到网关
     * @return Transfer
     * @throws Exception
     */
    public function gatewayHandle(): Transfer
    {
        if ($this->trade_channel == Pay::CHANNEL_WECHAT) {
            $this->markFailed('Failed', '暂不支持微信通道。');
        } elseif ($this->trade_channel == Pay::CHANNEL_ALIPAY) {
            $config = [
                'out_biz_no' => (string)$this->id,
                'trans_amount' => $this->amount / 100,
                'product_code' => 'TRANS_ACCOUNT_NO_PWD',
                'payee_info' => [
                    'identity' => $this->recipient['account'],
                    'identity_type' => $this->recipient['account_type'],
                    'name' => $this->recipient['name']
                ],
                'remark' => $this->description,
            ];
            try {
                $response = Pay::alipay()->transfer($config);
                if ((isset($response->code) && $response->code == 10000) && ($response->status == 'SUCCESS' || $response->status == 'DEALING')) {
                    $this->markSucceeded($response->order_id, $response->toArray());
                } else {
                    $this->markFailed((string)$response->sub_code, $response->sub_msg);
                }
            } catch (Exception $exception) {//设置提现失败
                $this->markFailed('FAIL', $exception->getMessage());
            }
        }
        return $this;
    }
}
