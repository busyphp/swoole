<?php

namespace BusyPHP\swoole;

use Smf\ConnectionPool\ConnectionPool;
use think\helper\Arr;

/**
 * 连接池类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2022 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2022/1/28 8:46 PM Pool.php $
 */
class Pool
{
    /**
     * @var ConnectionPool[]
     */
    protected $pools = [];
    
    
    /**
     * 添加一个连接池
     * @param string         $name 连接池名称
     * @param ConnectionPool $pool 连接池对象
     * @return Pool
     */
    public function add(string $name, ConnectionPool $pool) : self
    {
        $pool->init();
        $this->pools[$name] = $pool;
        
        return $this;
    }
    
    
    /**
     * 获取特定的连接池
     * @param string $name 连接池名称
     * @return ConnectionPool
     */
    public function get(string $name) : ?ConnectionPool
    {
        return $this->pools[$name] ?? null;
    }
    
    
    /**
     * 关闭特定的连接池
     * @param string $name 连接池名称
     * @return bool
     */
    public function close(string $name) : bool
    {
        $pool = $this->get($name);
        if (!$pool) {
            return false;
        }
        
        return $pool->close();
    }
    
    
    /**
     * 获取所有连接池
     * @return array<string,ConnectionPool>
     */
    public function getAll() : array
    {
        return $this->pools;
    }
    
    
    /**
     * 关闭所有池
     */
    public function closeAll()
    {
        foreach ($this->pools as $pool) {
            $pool->close();
        }
    }
    
    
    /**
     * @param string $key
     * @return ConnectionPool
     */
    public function __get(string $key)
    {
        return $this->get($key);
    }
    
    
    /**
     * 生成连接池配置
     * @param array $config
     * @return array
     */
    public static function pullPoolConfig(&$config) : array
    {
        return [
            'minActive'         => Arr::pull($config, 'min_active', 0),
            'maxActive'         => Arr::pull($config, 'max_active', 10),
            'maxWaitTime'       => Arr::pull($config, 'max_wait_time', 5),
            'maxIdleTime'       => Arr::pull($config, 'max_idle_time', 20),
            'idleCheckInterval' => Arr::pull($config, 'idle_check_interval', 10),
        ];
    }
}
