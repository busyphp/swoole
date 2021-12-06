<?php

namespace BusyPHP\swoole\pool;

use Smf\ConnectionPool\Connectors\ConnectorInterface;

class Connector implements ConnectorInterface
{
    protected $connector;
    
    
    public function __construct($connector)
    {
        $this->connector = $connector;
    }
    
    
    /**
     * 连接到指定的服务器并返回连接资源
     * @param array $config
     * @return mixed
     */
    public function connect(array $config)
    {
        return call_user_func($this->connector, $config);
    }
    
    
    /**
     * 断开连接并释放资源
     * @param mixed $connection
     */
    public function disconnect($connection)
    {
    }
    
    
    /**
     * 是否建立了连接
     * @param mixed $connection
     * @return bool
     */
    public function isConnected($connection) : bool
    {
        return true;
    }
    
    
    /**
     * 重置连接
     * @param mixed $connection
     * @param array $config
     */
    public function reset($connection, array $config)
    {
    }
    
    
    /**
     * 验证连接
     * @param mixed $connection
     * @return bool
     */
    public function validate($connection) : bool
    {
        return true;
    }
}
