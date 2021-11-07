<?php

namespace BusyPHP\swoole\task;

use BusyPHP\App;
use BusyPHP\exception\ClassNotImplementsException;
use BusyPHP\swoole\contract\task\TaskWorkerInterface;
use BusyPHP\swoole\task\parameter\TaskParameter;
use Swoole\Server;
use Swoole\Server\Task;

/**
 * Task Job
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/7 上午12:19 Job.php $
 */
class Job
{
    /**
     * @var string
     */
    public $worker;
    
    /**
     * @var mixed
     */
    public $data;
    
    
    /**
     * TaskParams constructor.
     * @param string $worker
     * @param mixed  $data
     */
    public function __construct(string $worker, $data)
    {
        $this->worker = $worker;
        $this->data   = $data;
    }
    
    
    /**
     * 执行
     * @param App    $app
     * @param Server $server
     * @param Task   $task
     */
    public function run(App $app, Server $server, Task $task)
    {
        if (!is_subclass_of($this->worker, TaskWorkerInterface::class)) {
            throw new ClassNotImplementsException($this->worker, TaskWorkerInterface::class);
        }
        
        $result = call_user_func_array([
            $this->worker,
            'onTask'
        ], [new TaskParameter($app, $server, $task, $this->data)]);
        if ($result === true) {
            $task->finish($this->data);
        }
    }
}