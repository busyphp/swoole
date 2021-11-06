<?php

namespace BusyPHP\swoole\contract;

use BusyPHP\App;
use BusyPHP\swoole\gateway\Gateway;
use Swoole\Server;
use think\Container;

/**
 * 网关基本类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/6 下午2:46 BaseGateway.php $
 */
abstract class BaseGateway
{
    /**
     * @var App
     */
    protected $app;
    
    /**
     * @var bool
     */
    private $isSwoole;
    
    /**
     * @var bool
     */
    private $must;
    
    
    public function __construct(App $app)
    {
        $this->app      = $app;
        $this->isSwoole = $this->app instanceof \BusyPHP\swoole\App;
    }
    
    
    /**
     * 单例模式
     * @return static
     */
    public static function init() : self
    {
        return Container::getInstance()->make(static::class);
    }
    
    
    /**
     * 设置强制执行
     * @param bool $must
     */
    public function setMust(bool $must)
    {
        if ($this->isSwoole && $must) {
            $this->must = true;
        } else {
            $this->must = false;
        }
    }
    
    
    /**
     * 是否可以执行指令
     * @return bool
     */
    protected function canRun() : bool
    {
        return $this->isSwoole || $this->must;
    }
    
    
    /**
     * 获取服务
     * @return Server
     */
    protected function server() : Server
    {
        return Container::getInstance()->make(Server::class);
    }
    
    
    /**
     * 获取网关并发送数据
     * @param string $method
     * @param array  $args
     * @return mixed
     */
    protected function gateway(string $method, array $args)
    {
        return Gateway::init()->send(static::class, $method, $args);
    }
}