<?php

namespace BusyPHP\swoole\concerns;

use BusyPHP\queue\event\JobFailed;
use BusyPHP\queue\FailedJob;
use BusyPHP\queue\Worker;
use Swoole\Process;
use Swoole\Runtime;
use Swoole\Server;
use Swoole\Timer;
use think\helper\Arr;

/**
 * 准备队列
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/5 上午10:10 InteractsWithQueue.php $
 * @method Server getServer()
 */
trait InteractsWithQueue
{
    /**
     * 准备队列服务
     */
    protected function prepareQueue()
    {
        if (!$this->getConfig('queue.enable', false)) {
            return;
        }
        
        $this->listenQueueEvents();
        $this->waitCoordinator('workerStart');
        
        $workers = $this->getConfig('queue.workers', []);
        foreach ($workers as $queue => $options) {
            // 队列驱动名称
            $connection = Arr::get($options, 'connection', null);
            
            // 启动多个work并行执行
            $number = Arr::get($options, 'number', 0);
            $number = $number <= 1 ? 1 : $number;
            for ($i = 0; $i < $number; $i++) {
                $this->getServer()
                    ->addProcess(new Process(function(Process $process) use ($options, $connection, $queue, $i) {
                        Runtime::enableCoroutine();
                        
                        $this->clearCache();
                        $this->prepareApplication();
                        $this->setProcessName("queue {$queue} progress {$i}");
                        $this->bindRpcInterface();
                        
                        $delay   = Arr::get($options, 'delay', 0);
                        $sleep   = Arr::get($options, 'sleep', 3);
                        $tries   = Arr::get($options, 'tries', 0);
                        $timeout = Arr::get($options, 'timeout', 60);
                        
                        /** @var Worker $worker */
                        $worker = $this->container->make(Worker::class);
                        
                        while (true) {
                            $timer = Timer::after($timeout * 1000, function() use ($process) {
                                $process->exit();
                            });
                            
                            $this->runInSandbox(function() use ($worker, $connection, $queue, $delay, $sleep, $tries) {
                                $worker->runNextJob($connection, $queue, $delay, $sleep, $tries);
                            });
                            
                            Timer::clear($timer);
                        }
                    }, false, 0, true));
            }
        }
    }
    
    
    /**
     * 注册事件
     */
    protected function listenQueueEvents()
    {
        $this->container->event->listen(JobFailed::class, function(JobFailed $event) {
            $this->logQueueFailedJob($event);
        });
    }
    
    
    /**
     * 记录失败任务
     * @param JobFailed $event
     */
    protected function logQueueFailedJob(JobFailed $event)
    {
        /** @var FailedJob $failer */
        $failer = $this->container['queue.failer'];
        $failer->log($event->connection, $event->job->getQueue(), $event->job->getRawBody(), $event->exception);
    }
}
