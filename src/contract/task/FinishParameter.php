<?php

namespace BusyPHP\swoole\contract\task;

use BusyPHP\App;
use Swoole\Server;

/**
 * @see TaskInterface
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2019 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2020/11/11 下午10:03 上午 FinishParameter.php $
 */
class FinishParameter
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
     * 原数据，即投递到任务的数据
     * @var mixed
     */
    public $originalData;
    
    /**
     * 新数据，即 {@see TaskInterface::onTaskRun()} 执行完成以后返回的数据，分三种情况:
     * - 异步任务：由{@see Task::finish()}返回;
     * - 同步等待任务：false 则任务执行超时。否则由 {@see Task::finish()} 返回;
     * - 同步等待并发任务：返回结果数组，结果的顺序与投递的数据相同，返回的结果数据中不包含超时的任务
     * @var mixed
     */
    public $finishData;
    
    /**
     * 异步任务ID
     * @var int|null
     */
    public $taskId;
    
    
    /**
     * TaskFinishStruct constructor.
     * @param App      $app
     * @param Server   $server
     * @param mixed    $originalData
     * @param mixed    $finishData
     * @param int|null $taskId
     */
    public function __construct(App $app, Server $server, $originalData, $finishData, ?int $taskId = null)
    {
        $this->app          = $app;
        $this->server       = $server;
        $this->originalData = $originalData;
        $this->finishData   = $finishData;
        $this->taskId       = $taskId;
    }
}