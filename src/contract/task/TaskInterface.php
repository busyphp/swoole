<?php

namespace BusyPHP\swoole\contract\task;

/**
 * 任务接口类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/6 下午10:39 TaskWorkerInterface.php $
 */
interface TaskInterface
{
    /**
     * 执行任务
     * @param TaskParameter $parameter
     * @return mixed
     */
    public static function onTaskRun(TaskParameter $parameter);
    
    
    /**
     * 任务执行完毕
     * - 在 worker 进程中有效，其它进程不会触发
     * @param FinishParameter $parameter
     */
    public static function onTaskFinish(FinishParameter $parameter) : void;
}