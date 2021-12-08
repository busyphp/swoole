<?php

namespace BusyPHP\swoole\contract\timer;

use BusyPHP\App;
use BusyPHP\exception\ClassNotImplementsException;
use BusyPHP\swoole\contract\task\TaskInterface;
use BusyPHP\swoole\task\Job;
use RuntimeException;
use Swoole\Server;

/**
 * @see TimerInterface
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/8 下午10:55 TimerParameter.php $
 */
class TimerParameter
{
    /**
     * 定时器ID
     * @var int
     */
    public $timeId;
    
    /**
     * 服务对象
     * @var Server
     */
    public $server;
    
    /**
     * @var App
     */
    public $app;
    
    
    /**
     * TaskTimerStruct constructor.
     * @param int    $timeId 定时器ID
     * @param Server $server 服务对象
     * @param App    $app
     */
    public function __construct(int $timeId, Server $server, App $app)
    {
        $this->timeId = $timeId;
        $this->server = $server;
        $this->app    = $app;
    }
    
    
    /**
     * 检测是否允许投递数据至异步任务
     * @return bool
     */
    public function status() : bool
    {
        $stats = $this->server->stats();
        if (($stats['task_idle_worker_num'] ?? 0) == 0) {
            return false;
        }
        
        if (($stats['tasking_num'] ?? 0) > 0) {
            return false;
        }
        
        // Worker 进程忙碌中
        // Swoole 版本 >= v4.5.0RC1 可用
        if (method_exists($this->server, 'getWorkerStatus') && $this->server->getWorkerStatus() === SWOOLE_WORKER_BUSY) {
            return false;
        }
        
        return true;
    }
    
    
    /**
     * 投递异步任务
     * @param string $worker
     * @param mixed  $data
     */
    public function taskAsync(string $worker, $data)
    {
        if (!$this->status()) {
            throw new RuntimeException("There are no idle processes or task processes busy");
        }
        
        if (!is_subclass_of($worker, TaskInterface::class)) {
            throw new ClassNotImplementsException($worker, TaskInterface::class);
        }
        
        $this->server->task(new Job($worker, $data));
    }
}