<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 */

declare(strict_types=1);

namespace Larva\Pay;

use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;
use Yansongda\Pay\Pay as YansongdaPay;

/**
 * 支付服务
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class PayServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => base_path('resources/views/vendor/pay'),
            ], 'laravel-assets');
            $this->publishes(
                [
                __DIR__ . '/../config/pay.php' => config_path('pay.php'),],
                'pay-config'
            );
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Yansongda\Pay\Exception\ContainerException
     * @throws \Yansongda\Pay\Exception\ServiceNotFoundException
     */
    public function register()
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/pay.php', 'pay');

        YansongdaPay::config(Container::getInstance()->make('config')->get('pay'));

        $this->app->singleton('pay.alipay', function () {
            return YansongdaPay::alipay();
        });

        $this->app->singleton('pay.wechat', function () {
            return YansongdaPay::wechat();
        });
    }

    /**
     * Get services.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['pay.alipay', 'pay.wechat'];
    }
}
