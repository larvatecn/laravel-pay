<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 */

declare(strict_types=1);

namespace Larva\Pay\Http\Controllers;

use Illuminate\Contracts\Routing\ResponseFactory;
use Larva\Pay\Pay;

/**
 * 回调页面
 * @author Tongle Xu <xutongle@msn.com>
 */
class CallbackController
{
    /**
     * The response factory implementation.
     *
     * @var ResponseFactory
     */
    protected ResponseFactory $response;

    /**
     * CodeController constructor.
     * @param ResponseFactory $response
     */
    public function __construct(ResponseFactory $response)
    {
        $this->response = $response;
    }

    /**
     * 支付宝PC和手机付款的回调页面
     */
    public function alipay()
    {
        $params = Pay::alipay()->callback();
        $result = Pay::alipay()->find(['out_trade_no' => $params['out_trade_no']]);
        if (isset($result['trade_status']) && ($result['trade_status'] == 'TRADE_SUCCESS' || $result['trade_status'] == 'TRADE_FINISHED')) {
            $charge = Pay::getCharge($result['out_trade_no']);
            $charge->markSucceeded($result['trade_no'], $result->toArray());
            if (isset($charge->metadata['return_url'])) {
                $this->response->redirectTo($charge->metadata['return_url']);
            }
        }
        $this->response->view('transaction:return', ['charge' => $charge ?? null]);
    }

    /**
     * 扫码付成功回调
     * @param string $id
     */
    public function scan(string $id)
    {
        $charge = Pay::getCharge($id);
        if ($charge->paid) {
            if (isset($charge->metadata['return_url'])) {
                $this->response->redirectTo($charge->metadata['return_url']);
            } else {
                $this->response->view('transaction:return', ['charge' => $charge]);
            }
        }
    }
}
