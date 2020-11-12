<?php

namespace BusyPHP\swoole;

use think\App;

/**
 * Swoole配置
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2019 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2020/11/11 下午11:28 下午 SwooleConfig.php $
 * @property App $app;
 */
trait SwooleConfig
{
    private $isLoad = false;
    
    
    /**
     * 获取配置
     * @param string $name 配置名称
     * @param mixed  $default 默认值
     * @return mixed
     */
    public function getSwooleConfig($name, $default = null)
    {
        if (!$this->isLoad) {
            $this->app->config->load($this->app->getRootPath() . 'config' . DIRECTORY_SEPARATOR . 'extend' . DIRECTORY_SEPARATOR . 'swoole.php', 'busy_swoole');
            
            $this->isLoad = true;
        }
        
        return $this->app->config->get('busy_swoole.' . $name, $default);
    }
}