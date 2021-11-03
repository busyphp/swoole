<?php

namespace BusyPHP\swoole\pool;

use think\Config;
use think\db\ConnectionInterface;
use BusyPHP\swoole\pool\proxy\Connection;

/**
 * Class Db
 * @package BusyPHP\swoole\pool
 * @property Config $config
 */
class Db extends \BusyPHP\Db
{
    protected function createConnection(string $name) : ConnectionInterface
    {
        return new Connection(function() use ($name) {
            return parent::createConnection($name);
        }, $this->config->get('swoole.pool.db', []));
    }
    
    
    protected function getConnectionConfig(string $name) : array
    {
        $config = parent::getConnectionConfig($name);
        
        //打开断线重连
        $config['break_reconnect'] = true;
        
        return $config;
    }
}