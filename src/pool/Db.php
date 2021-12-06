<?php

namespace BusyPHP\swoole\pool;

use think\Config;
use think\db\ConnectionInterface;
use BusyPHP\swoole\pool\proxy\Connection;

/**
 * 数据库连接池
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/6 上午9:27 Db.php $
 * @property Config $config
 */
class Db extends \BusyPHP\Db
{
    protected function createConnection(string $name) : ConnectionInterface
    {
        return new Connection(new class(function() use ($name) {
            return parent::createConnection($name);
        }) extends Connector {
            public function disconnect($connection)
            {
                if ($connection instanceof ConnectionInterface) {
                    $connection->close();
                }
            }
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
