<?php

namespace BusyPHP\swoole\contract\task;

use BusyPHP\App;
use Swoole\Server;
use Swoole\Server\Task;

/**
 * @see TaskInterface
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2019 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2020/11/11 下午10:03 上午 TaskParameter.php $
 */
class TaskParameter
{
    /**
     * @var App
     */
    public $app;
    
    /**
     * @var Server
     */
    public $server;
    
    /**
     * 投递的数据
     * @var mixed
     */
    public $data;
    
    /**
     * @var Task
     */
    public $task;
    
    
    /**
     * TaskFinishStruct constructor.
     * @param App    $app
     * @param Server $server
     * @param Task   $task
     * @param        $data
     */
    public function __construct(App $app, Server $server, Task $task, $data)
    {
        $this->app    = $app;
        $this->server = $server;
        $this->task   = $task;
        $this->data   = $data;
    }
}