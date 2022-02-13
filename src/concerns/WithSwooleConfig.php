<?php

namespace BusyPHP\swoole\concerns;

use BusyPHP\App;
use think\Config;

/**
 * 配置类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2022 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2022/2/13 11:24 AM WithConfig.php $
 * @property App $container
 * @property App $app
 */
trait WithSwooleConfig
{
    /**
     * 获取配置
     * @param string      $name
     * @param null|mixed  $default
     * @param Config|null $config
     * @return mixed
     */
    public function getSwooleConfig(string $name, $default = null, ?Config $config = null)
    {
        if (!$config) {
            if (isset($this->container)) {
                $app = $this->container;
            } elseif (isset($this->app)) {
                $app = $this->app;
            } else {
                $app = App::getInstance();
            }
            $config = $app->config;
        }
        
        return $config->get("busy-swoole.{$name}", $default);
    }
}