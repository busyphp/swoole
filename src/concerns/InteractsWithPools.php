<?php

namespace BusyPHP\swoole\concerns;

use BusyPHP\App;
use Smf\ConnectionPool\ConnectionPool;
use Smf\ConnectionPool\Connectors\ConnectorInterface;
use Swoole\Server;
use think\helper\Arr;
use BusyPHP\swoole\Pool;

/**
 * 连接池
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/6 下午9:28 InteractsWithPools.php $
 * @property App $app
 * @method Server getServer()
 */
trait InteractsWithPools
{
    /**
     * @return Pool
     */
    public function getPools()
    {
        return $this->app->make(Pool::class);
    }
    
    
    protected function preparePools()
    {
        $createPools = function() {
            /** @var Pool $pool */
            $pools = $this->getPools();
            
            foreach ($this->getConfig('pool', []) as $name => $config) {
                $type = Arr::pull($config, 'type');
                if ($type && is_subclass_of($type, ConnectorInterface::class)) {
                    $pool = new ConnectionPool(Pool::pullPoolConfig($config), $this->app->make($type), $config);
                    $pools->add($name, $pool);
                    //注入到app
                    $this->app->instance("swoole.pool.{$name}", $pool);
                }
            }
        };
        
        $this->onEvent('workerStart', $createPools);
    }
}
