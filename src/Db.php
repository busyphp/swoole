<?php

namespace BusyPHP\swoole;

use think\db\ConnectionInterface;
use think\swoole\pool\proxy\Connection;

/**
 * 数据库类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/2 下午下午9:44 Db.php $
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
