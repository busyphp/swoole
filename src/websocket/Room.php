<?php

namespace BusyPHP\swoole\websocket;

use BusyPHP\swoole\concerns\WithSwooleConfig;
use think\Manager;
use BusyPHP\swoole\websocket\room\Table;

/**
 * Class Room
 * @package BusyPHP\swoole\websocket
 * @mixin Table
 */
class Room extends Manager
{
    use WithSwooleConfig;
    
    protected $namespace = "\\BusyPHP\\swoole\\websocket\\room\\";
    
    
    protected function resolveConfig(string $name)
    {
        return $this->getSwooleConfig("websocket.server.room.{$name}", []);
    }
    
    
    /**
     * 默认驱动
     * @return string|null
     */
    public function getDefaultDriver()
    {
        return $this->getSwooleConfig('websocket.server.room.type', 'table');
    }
}
