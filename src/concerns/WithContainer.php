<?php

namespace BusyPHP\swoole\concerns;

use BusyPHP\App;
use think\console\Output;
use think\exception\Handle;
use Throwable;

/**
 * WithContainer
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2022 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2022/2/13 11:22 AM WithContainer.php $
 */
trait WithContainer
{
    /**
     * @var App
     */
    protected $container;
    
    
    /**
     * Manager constructor.
     * @param App $container
     */
    public function __construct(App $container)
    {
        $this->container = $container;
    }
    
    
    /**
     * 获取容器对象
     * @return App
     */
    protected function getContainer() : App
    {
        return $this->container;
    }
    
    
    /**
     * 触发事件
     * @param string $event
     * @param null   $params
     */
    protected function triggerEvent(string $event, $params = null) : void
    {
        $this->container->event->trigger("swoole.{$event}", $params);
    }
    
    
    /**
     * 监听事件
     * @param string $event 事件名称
     * @param mixed  $listener 监听操作（或者类名）
     * @param bool   $first 是否优先执行
     */
    public function onEvent(string $event, $listener, bool $first = false) : void
    {
        $this->container->event->listen("swoole.{$event}", $listener, $first);
    }
    
    
    /**
     * 处理异常
     * @param Throwable $e
     */
    public function logServerError(Throwable $e)
    {
        /** @var Handle $handle */
        $handle = $this->container->make(Handle::class);
        
        $handle->renderForConsole(new Output(), $e);
        
        $handle->report($e);
    }
}
