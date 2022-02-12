<?php

namespace BusyPHP\swoole;

use BusyPHP\queue\contract\JobInterface;
use BusyPHP\queue\Job;

/**
 * QueuePingJob
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2022 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2022/2/12 9:43 PM QueuePingJob.php $
 */
class QueueTestPingJob implements JobInterface
{
    /**
     * 执行任务
     * @param Job   $job 任务对象
     * @param mixed $data 发布任务时自定义的数据
     */
    public function fire(Job $job, $data) : void
    {
        $job->delete();
    }
}