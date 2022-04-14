<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 */

declare(strict_types=1);

namespace Larva\Pay\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Event;
use Larva\Pay\Casts\Failure;
use Larva\Pay\Events\ChargeClosed;
use Larva\Pay\Events\ChargeFailed;
use Larva\Pay\Events\ChargeSucceeded;
use Larva\Pay\Pay;
use Larva\Pay\PayException;
use Yansongda\Pay\Exception\ContainerException;
use Yansongda\Pay\Exception\InvalidParamsException;
use Yansongda\Pay\Exception\ServiceNotFoundException;
use Yansongda\Supports\Collection;

/**
 * 支付模型
 * @property int $id 收款流水号
 * @property string $trade_channel 支付渠道
 * @property string $trade_type 支付类型
 * @property string $transaction_no 支付网关交易号
 * @property string $order_id 订单ID
 * @property string $order_type 订单类型
 * @property string $subject 支付标题
 * @property string $description 描述
 * @property int $total_amount 支付金额，单位分
 * @property int $refunded_amount 已退款钱数
 * @property string $currency 支付币种
 * @property string $state 交易状态
 * @property string $client_ip 客户端IP
 * @property array $metadata 元信息
 * @property array $credential 客户端支付凭证
 * @property Failure $failure 错误信息
 * @property array $extra 网关返回的信息
 * @property CarbonInterface|null $succeed_at 支付完成时间
 * @property CarbonInterface|null $expired_at 过期时间
 * @property CarbonInterface $created_at 创建时间
 * @property CarbonInterface|null $updated_at 更新时间
 * @property CarbonInterface|null $deleted_at 软删除时间
 *
 * @property Model $order 触发该收款的订单模型
 * @property Refund $refunds 退款列表
 *
 * @property-read bool $paid 是否已付款
 * @property-read bool $refunded 是否有退款
 * @property-read bool $reversed 是否已撤销
 * @property-read bool $closed 是否已关闭
 * @property-read string $stateDesc 状态描述
 * @property-read string $outTradeNo
 * @property-read int $refundableAmount 可退款金额
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class Charge extends Model
{
    use SoftDeletes, Traits\UsingDatetimeAsPrimaryKey, Traits\DateTimeFormatter;

    public const STATE_SUCCESS = 'SUCCESS';
    public const STATE_REFUND = 'REFUND';
    public const STATE_NOTPAY = 'NOTPAY';
    public const STATE_CLOSED = 'CLOSED';
    public const STATE_REVOKED = 'REVOKED';
    public const STATE_USERPAYING = 'USERPAYING';
    public const STATE_PAYERROR = 'PAYERROR';
    public const STATE_ACCEPT = 'ACCEPT';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pay_charges';

    /**
     * @var bool 主键自增
     */
    public $incrementing = false;

    /**
     * @var array 批量赋值属性
     */
    public $fillable = [
        'id', 'trade_channel', 'trade_type', 'transaction_no', 'subject', 'description', 'total_amount', 'refunded_amount',
        'currency', 'state', 'client_ip', 'metadata', 'credential', 'extra', 'failure', 'succeed_at', 'expired_at'
    ];

    /**
     * 这个属性应该被转换为原生类型.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'int',
        'trade_channel' => 'string',
        'trade_type' => 'string',
        'transaction_no' => 'string',
        'subject' => 'string',
        'description' => 'string',
        'total_amount' => 'int',
        'refunded_amount' => 'int',
        'currency' => 'string',
        'state' => 'string',
        'client_ip' => 'string',
        'metadata' => 'array',
        'extra' => 'array',
        'credential' => 'array',
        'failure' => Failure::class
    ];

    /**
     * 应该被调整为日期的属性
     *
     * @var array
     */
    protected $dates = [
        'succeed_at', 'expired_at', 'created_at', 'updated_at', 'deleted_at'
    ];

    /**
     * 交易状态，枚举值
     * @var array|string[]
     */
    protected static array $stateMaps = [
        self::STATE_SUCCESS => '支付成功',
        self::STATE_REFUND => '转入退款',
        self::STATE_NOTPAY => '未支付',
        self::STATE_CLOSED => '已关闭',
        self::STATE_REVOKED => '已撤销',//已撤销（仅付款码支付会返回）
        self::STATE_USERPAYING => '用户支付中',//用户支付中（仅付款码支付会返回）
        self::STATE_PAYERROR => '支付失败',//支付失败（仅付款码支付会返回）
        self::STATE_ACCEPT => '已接收，等待扣款',
    ];

    /**
     * 交易状态，枚举值
     * @var array|string[]
     */
    protected static array $stateDots = [
        self::STATE_SUCCESS => 'success',
        self::STATE_REFUND => 'warning',
        self::STATE_NOTPAY => 'info',
        self::STATE_CLOSED => 'info',
        self::STATE_REVOKED => 'info',//已撤销（仅付款码支付会返回）
        self::STATE_USERPAYING => 'info',//用户支付中（仅付款码支付会返回）
        self::STATE_PAYERROR => 'error',//支付失败（仅付款码支付会返回）
        self::STATE_ACCEPT => 'warning',
    ];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    public static function booted()
    {
        static::creating(function (Charge $model) {
            $model->id = $model->generateKey();
            $model->currency = $model->currency ?: 'CNY';
            $model->expired_at = $model->expired_at ?? $model->freshTimestamp()->addHours(24);//过期时间24小时
            $model->state = static::STATE_NOTPAY;
        });
        static::created(function (Charge $model) {
            if (!empty($model->trade_channel) && !empty($model->trade_type)) {//不为空就预下单
                $model->prePay();
            }
        });
    }

    /**
     * 获取 State Label
     * @return string[]
     */
    public static function getStateMaps(): array
    {
        return static::$stateMaps;
    }

    /**
     * 获取状态Dot
     * @return string[]
     */
    public static function getStateDots(): array
    {
        return static::$stateDots;
    }

    /**
     * 关联订单
     * @return MorphTo
     */
    public function order(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 关联退款
     * @return HasMany
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * 是否已付款
     * @return bool
     */
    public function getPaidAttribute(): bool
    {
        return $this->state == static::STATE_SUCCESS || $this->state == static::STATE_REFUND;
    }

    /**
     * 是否有退款
     * @return bool
     */
    public function getRefundedAttribute(): bool
    {
        return $this->state == static::STATE_REFUND;
    }

    /**
     * 是否已撤销
     * @return bool
     */
    public function getReversedAttribute(): bool
    {
        return $this->state == static::STATE_REVOKED;
    }

    /**
     * 是否已关闭
     * @return bool
     */
    public function getClosedAttribute(): bool
    {
        return $this->state == static::STATE_CLOSED;
    }

    /**
     * 状态描述
     * @return string
     */
    public function getStateDescAttribute(): string
    {
        return static::$stateMaps[$this->state] ?? '未知状态';
    }

    /**
     * 获取可退款钱数
     * @return int
     */
    public function getRefundableAmountAttribute(): int
    {
        $refundableAmount = $this->total_amount - $this->refunded_amount;
        if ($refundableAmount > 0) {
            return $refundableAmount;
        }
        return 0;
    }

    /**
     * 获取OutTradeNo
     * @return string
     */
    public function getOutTradeNoAttribute(): string
    {
        return (string)$this->id;
    }

    /**
     * 设置已付款状态
     * @param string $transactionNo 支付渠道返回的交易流水号。
     * @param array $extra
     * @return bool
     */
    public function markSucceeded(string $transactionNo, array $extra = []): bool
    {
        if ($this->paid) {
            return true;
        }
        $state = $this->updateQuietly([
            'transaction_no' => $transactionNo,
            'expired_at' => null,
            'succeed_at' => $this->freshTimestamp(),
            'state' => static::STATE_SUCCESS,
            'credential' => [],
            'extra' => $extra
        ]);
        Event::dispatch(new ChargeSucceeded($this));
        return $state;
    }

    /**
     * 设置支付错误
     * @param string|int $code
     * @param string $desc
     * @return bool
     */
    public function markFailed($code, string $desc, array $extra = []): bool
    {
        $state = $this->updateQuietly([
            'state' => static::STATE_PAYERROR,
            'credential' => [],
            'failure' => ['code' => $code, 'desc' => $desc],
            'extra' => $extra
        ]);
        Event::dispatch(new ChargeFailed($this));
        return $state;
    }

    /**
     * 发起退款
     * @param string $reason 退款原因
     * @return Refund
     * @throws PayException
     */
    public function refund(string $reason): Refund
    {
        if (!$this->paid) {
            throw new PayException('Not paid, no refund.');
        } elseif ($this->refundableAmount == 0) {
            throw new PayException('No refundable amount.');
        } else {
            /** @var Refund $refund */
            $refund = $this->refunds()->create([
                'charge_id' => $this->id,
                'amount' => $this->total_amount,
                'reason' => $reason,
            ]);
            return $refund;
        }
    }

    /**
     * 关闭该笔收单
     * @return bool
     */
    public function close(): bool
    {
        if ($this->state == static::STATE_NOTPAY) {
            if ($this->trade_channel == Pay::CHANNEL_WECHAT) {
                Pay::wechat()->close(['out_trade_no' => $this->outTradeNo]);
                $this->updateQuietly(['state' => static::STATE_CLOSED, 'credential' => []]);
                Event::dispatch(new ChargeClosed($this));
                return true;
            } elseif ($this->trade_channel == Pay::CHANNEL_ALIPAY) {
                $result = Pay::alipay()->close(['out_trade_no' => $this->outTradeNo]);
                if (isset($result->code) && $result->code == 10000) {
                    $this->updateQuietly(['state' => static::STATE_CLOSED, 'credential' => [], 'extra' => $result->toArray()]);
                    Event::dispatch(new ChargeClosed($this));
                    return true;
                } else {
                    $this->updateQuietly(['extra' => $result->toArray()]);
                    return false;
                }
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * 获取指定渠道的支付凭证
     * @param string $channel 渠道
     * @param string $type 通道类型
     * @param array $metadata 元数据
     * @return array
     */
    public function getCredential(string $channel, string $type, array $metadata = []): array
    {
        $this->update(['trade_channel' => $channel, 'trade_type' => $type, 'metadata' => $metadata]);
        $this->prePay();
        $this->refresh();
        return $this->credential;
    }

    /**
     * 订单付款预下单
     */
    public function prePay()
    {
        $order = [
            'out_trade_no' => $this->outTradeNo,
        ];
        if ($this->trade_channel == Pay::CHANNEL_WECHAT) {
            $order['description'] = $this->description ?? $this->subject;
            $order['amount'] = [
                'total' => $this->total_amount,
                'currency' => 'CNY',
            ];
            if ($this->expired_at) {
                $order['time_expire'] = $this->expired_at->toRfc3339String();
            }
            $order['scene_info'] = [
                'payer_client_ip' => $this->client_ip
            ];
            if ($this->trade_type == 'wap') {
                $order['scene_info']['h5_info']['type'] = 'Wap';
                $order['scene_info']['h5_info']['app_name'] = config('app.name');
                $order['scene_info']['h5_info']['app_url'] = config('app.url');
            }
            if ($this->metadata && isset($this->metadata['openid'])) {
                $order['payer']['openid'] = $this->metadata['openid'];
            }
            $order['notify_url'] = route('transaction.notify.wechat');
        } elseif ($this->trade_channel == Pay::CHANNEL_ALIPAY) {
            $order['total_amount'] = $this->total_amount / 100;//总钱数，单位元
            $order['subject'] = $this->subject;
            if ($this->description) {
                $order['body'] = $this->description;
            }
            if ($this->expired_at) {
                $order['time_expire'] = $this->expired_at->format('Y-m-d H:i:s');
            }
            if ($this->metadata && isset($this->metadata['buyer_id'])) {
                $order['buyer_id'] = $this->metadata['buyer_id'];
            }
            $order['_notify_url'] = route('transaction.notify.alipay');
            if ($this->trade_type == 'wap') {
                $order['_return_url'] = route('transaction.callback.alipay');
                $order['quit_url'] = config('app.url');
            } elseif ($this->trade_type == Pay::TRADE_TYPE_WEB) {
                $order['_return_url'] = route('transaction.callback.alipay');
            }
        } else {
            throw new PayException('The channel does not exist.');
        }
        try {
            $credential = Pay::getChannel($this->trade_channel)->{$this->trade_type}($order);
            if ($credential instanceof Collection) {
                $credential = $credential->toArray();
            } elseif ($credential instanceof \GuzzleHttp\Psr7\Response) {
                $credential = $credential->getBody()->getContents();
                if ($this->trade_channel == Pay::CHANNEL_ALIPAY && $this->trade_type == 'app') {
                    $params = [];
                    parse_str($credential, $params);
                    $credential = $params;
                } else {//WEB H5
                    $credential = ['html' => $credential];
                }
            }
            $this->updateQuietly(['credential' => $credential]);
        } catch (ContainerException | InvalidParamsException | ServiceNotFoundException $e) {
            $this->markFailed($e->getCode(), $e->getMessage());
            return;
        }
    }
}
