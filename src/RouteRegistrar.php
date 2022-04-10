<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 */

declare(strict_types=1);

namespace Larva\Pay;

use Illuminate\Contracts\Routing\Registrar as Router;

/**
 * 路由注册
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class RouteRegistrar
{
    /**
     * The router implementation.
     *
     * @var Router
     */
    protected $router;

    /**
     * Create a new route registrar instance.
     *
     * @param Router $router
     * @return void
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Register routes for transient tokens, clients, and personal access tokens.
     *
     * @return void
     */
    public function all()
    {
        $this->forNotify();
    }

    /**
     * Register the routes needed for notify.
     *
     * @return void
     */
    public function forNotify()
    {
        $this->router->match(['get', 'post'], 'notify/wechat', [//微信通知
            'uses' => 'NotifyController@wechat',
            'as' => 'transaction.notify.wechat',
        ]);
        $this->router->match(['get', 'post'], 'notify/alipay', [//支付宝通知
            'uses' => 'NotifyController@alipay',
            'as' => 'transaction.notify.alipay',
        ]);
        $this->router->match(['get', 'post'], 'callback/alipay', [//支付宝回调
            'uses' => 'CallbackController@alipay',
            'as' => 'transaction.callback.alipay',
        ]);
        $this->router->match(['get'], 'callback/{id}', [//扫码回调
            'uses' => 'CallbackController@scan',
            'as' => 'transaction.callback.scan',
        ]);
        $this->router->match(['get'], 'charge/{id}', [//支付状态查询
            'uses' => 'ChargeController@query',
            'as' => 'transaction.charge.query',
        ]);
    }
}
