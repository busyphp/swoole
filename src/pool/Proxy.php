<?php

namespace BusyPHP\swoole\pool;

use Closure;
use RuntimeException;
use Smf\ConnectionPool\ConnectionPool;
use Smf\ConnectionPool\Connectors\ConnectorInterface;
use Swoole\Coroutine;
use BusyPHP\swoole\coroutine\Context;
use BusyPHP\swoole\Pool;

/**
 * 连接池基本类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/6 上午9:34 Proxy.php $
 */
abstract class Proxy
{
    const KEY_RELEASED = '__released';
    
    protected $pool;
    
    
    /**
     * Proxy constructor.
     * @param Closure|ConnectorInterface $connector
     * @param array                      $config
     * @param array                      $connectionConfig
     */
    public function __construct($connector, $config, array $connectionConfig = [])
    {
        if ($connector instanceof Closure) {
            $connector = new Connector($connector);
        }
        
        $this->pool = new ConnectionPool(Pool::pullPoolConfig($config), $connector, $connectionConfig);
        $this->pool->init();
    }
    
    
    /**
     * 获取一个连接
     * @return mixed
     */
    protected function getPoolConnection()
    {
        return Context::rememberData('connection.' . spl_object_id($this), function() {
            $connection = $this->pool->borrow();
            
            $connection->{static::KEY_RELEASED} = false;
            
            Coroutine::defer(function() use ($connection) {
                //自动释放
                $this->releaseConnection($connection);
            });
            
            return $connection;
        });
    }
    
    
    /**
     * 释放连接
     * @param $connection
     */
    protected function releaseConnection($connection)
    {
        if ($connection->{static::KEY_RELEASED}) {
            return;
        }
        $connection->{static::KEY_RELEASED} = true;
        $this->pool->return($connection);
    }
    
    
    public function release()
    {
        $connection = $this->getPoolConnection();
        $this->releaseConnection($connection);
    }
    
    
    public function __call($method, $arguments)
    {
        $connection = $this->getPoolConnection();
        if ($connection->{static::KEY_RELEASED}) {
            throw new RuntimeException('Connection already has been released!');
        }
        
        return $connection->{$method}(...$arguments);
    }
}
