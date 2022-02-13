<?php

namespace BusyPHP\swoole\pool;

use BusyPHP\swoole\concerns\WithSwooleConfig;
use BusyPHP\swoole\pool\proxy\Store;

/**
 * 缓存连接池
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/6 上午9:29 Cache.php $
 */
class Cache extends \think\Cache
{
    use WithSwooleConfig;
    
    protected function createDriver(string $name)
    {
        return new Store(function() use ($name) {
            return parent::createDriver($name);
        }, $this->getSwooleConfig('pool.cache', []));
    }
}
