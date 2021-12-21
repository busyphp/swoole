<?php
declare(strict_types = 1);

namespace BusyPHP\swoole\websocket\room;

use Closure;
use InvalidArgumentException;
use Redis as PHPRedis;
use Smf\ConnectionPool\BorrowConnectionTimeoutException;
use Smf\ConnectionPool\ConnectionPool;
use Smf\ConnectionPool\Connectors\PhpRedisConnector;
use think\helper\Arr;
use BusyPHP\swoole\contract\websocket\WebsocketRoomInterface;
use BusyPHP\swoole\Manager;
use BusyPHP\swoole\Pool;

/**
 * Redis房间驱动
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/21 下午2:10 Redis.php $
 */
class Redis implements WebsocketRoomInterface
{
    /**
     * @var array
     */
    protected $config;
    
    /**
     * redis前缀
     * @var string
     */
    protected $prefix = 'swoole:';
    
    /**
     * @var Manager
     */
    protected $manager;
    
    /**
     * @var ConnectionPool
     */
    protected $pool;
    
    
    /**
     * RedisRoom constructor.
     * @param Manager $manager
     * @param array   $config
     */
    public function __construct(Manager $manager, array $config)
    {
        $this->manager = $manager;
        $this->config  = $config;
        
        if ($prefix = Arr::get($this->config, 'prefix')) {
            $this->prefix = $prefix;
        }
    }
    
    
    /**
     * 准备Redis
     * @return WebsocketRoomInterface
     */
    public function prepare() : WebsocketRoomInterface
    {
        // 初始化Redis
        $connector  = new PhpRedisConnector();
        $connection = $connector->connect($this->config);
        if (count($keys = $connection->keys("{$this->prefix}*"))) {
            $connection->del($keys);
        }
        
        $connector->disconnect($connection);
        
        // 启动驱动
        $this->manager->onEvent('workerStart', function() {
            $config     = $this->config;
            $this->pool = new ConnectionPool(Pool::pullPoolConfig($config), new PhpRedisConnector(), $config);
            $this->manager->getPools()->add("websocket.room", $this->pool);
        });
        
        return $this;
    }
    
    
    /**
     * 将FD和房间进行关系绑定
     * @param int          $fd FD
     * @param array|string $rooms 房间名称
     * @throws BorrowConnectionTimeoutException
     */
    public function bind(int $fd, $rooms)
    {
        $rooms = is_array($rooms) ? $rooms : [$rooms];
        
        $this->addValue((string) $fd, $rooms, WebsocketRoomInterface::DESCRIPTORS_KEY);
        
        foreach ($rooms as $room) {
            $this->addValue((string) $room, [$fd], WebsocketRoomInterface::ROOMS_KEY);
        }
    }
    
    
    /**
     * 删除FD和房间的绑定关系
     * @param int          $fd FD
     * @param array|string $rooms 房间名称
     * @throws BorrowConnectionTimeoutException
     */
    public function unbind(int $fd, $rooms = [])
    {
        $rooms = is_array($rooms) ? $rooms : [$rooms];
        $rooms = count($rooms) ? $rooms : $this->getRoomsByFd($fd);
        
        $this->removeValue((string) $fd, $rooms, WebsocketRoomInterface::DESCRIPTORS_KEY);
        
        foreach ($rooms as $room) {
            $this->removeValue($room, [$fd], WebsocketRoomInterface::ROOMS_KEY);
        }
    }
    
    
    /**
     * 通过房间名获取所有加入该房间的FD
     * @param string|int $room 房间名
     * @return array
     * @throws BorrowConnectionTimeoutException
     */
    public function getFdsByRoom($room) : array
    {
        return $this->getValue((string) $room, WebsocketRoomInterface::ROOMS_KEY) ?? [];
    }
    
    
    /**
     * 通过FD获取该FD加入的所有房间
     * @param int $fd FD
     * @return array
     * @throws BorrowConnectionTimeoutException
     */
    public function getRoomsByFd(int $fd) : array
    {
        return $this->getValue((string) $fd, WebsocketRoomInterface::DESCRIPTORS_KEY) ?? [];
    }
    
    
    /**
     * @param Closure $callable
     * @return mixed
     * @throws BorrowConnectionTimeoutException
     */
    protected function runWithRedis(Closure $callable)
    {
        $redis = $this->pool->borrow();
        try {
            return $callable($redis);
        } finally {
            $this->pool->return($redis);
        }
    }
    
    
    /**
     * 添加值
     * @param        $key
     * @param array  $values
     * @param string $table
     * @return $this
     * @throws BorrowConnectionTimeoutException
     */
    protected function addValue(string $key, array $values, string $table)
    {
        $this->checkTable($table);
        $redisKey = $this->getKey($key, $table);
        
        $this->runWithRedis(function(PHPRedis $redis) use ($redisKey, $values) {
            $pipe = $redis->multi(PHPRedis::PIPELINE);
            
            foreach ($values as $value) {
                $pipe->sadd($redisKey, $value);
            }
            
            $pipe->exec();
        });
        
        return $this;
    }
    
    
    /**
     * 删除值
     * @param string $key
     * @param array  $values
     * @param string $table
     * @return $this
     * @throws BorrowConnectionTimeoutException
     */
    protected function removeValue(string $key, array $values, string $table)
    {
        $this->checkTable($table);
        $redisKey = $this->getKey($key, $table);
        
        $this->runWithRedis(function(PHPRedis $redis) use ($redisKey, $values) {
            $pipe = $redis->multi(PHPRedis::PIPELINE);
            foreach ($values as $value) {
                $pipe->srem($redisKey, $value);
            }
            $pipe->exec();
        });
        
        return $this;
    }
    
    
    /**
     * 检测
     * @param string $table
     */
    protected function checkTable(string $table)
    {
        if (!in_array($table, [WebsocketRoomInterface::ROOMS_KEY, WebsocketRoomInterface::DESCRIPTORS_KEY])) {
            throw new InvalidArgumentException("Invalid table name: `{$table}`.");
        }
    }
    
    
    /**
     * 获取值
     * @param string $key
     * @param string $table
     * @return array
     * @throws BorrowConnectionTimeoutException
     */
    protected function getValue(string $key, string $table)
    {
        $this->checkTable($table);
        
        return $this->runWithRedis(function(PHPRedis $redis) use ($table, $key) {
            return $redis->smembers($this->getKey($key, $table));
        });
    }
    
    
    /**
     * 生成KEY
     * @param string $key
     * @param string $table
     * @return string
     */
    protected function getKey(string $key, string $table) : string
    {
        return "{$this->prefix}{$table}:{$key}";
    }
}
