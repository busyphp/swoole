<?php

namespace BusyPHP\swoole\concerns;

use BusyPHP\exception\ClassNotImplementsException;
use BusyPHP\swoole\contract\timer\TimerInterface;
use BusyPHP\swoole\contract\timer\TimerParameter;
use Closure;
use Swoole\Process;
use Swoole\Runtime;
use Swoole\Server;
use Swoole\Timer;

/**
 * 准备定时器
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/8 上午11:07 InteractsWithTimer.php
 * @method Server getServer();
 * @method runInSandbox(Closure $callback)
 */
trait InteractsWithTimer
{
    /** @var Server */
    private $server;
    
    
    /**
     * 准备定时服务
     */
    protected function prepareTimer()
    {
        if (!$this->getConfig('timer.enable', false)) {
            return;
        }
        
        $this->waitCoordinator('workerStart');
        $this->server = $this->getServer();
        $workers      = $this->getConfig('timer.workers', []);
        foreach ($workers as $worker) {
            if (!is_subclass_of($worker, TimerInterface::class)) {
                throw new ClassNotImplementsException($worker, TimerInterface::class);
            }
            
            $concurrency = $worker::onTimerGetConcurrency();
            $concurrency = $concurrency <= 1 ? 1 : $concurrency;
            for ($i = 0; $i < $concurrency; $i++) {
                $this->server->addProcess(new Process(function(Process $process) use ($worker, $i) {
                    Runtime::enableCoroutine();
                    
                    $this->clearCache();
                    $this->prepareApplication();
                    $this->setProcessName("timer {$worker} progress {$i}");
                    
                    // 阻塞模式
                    if ($worker::onTimerGetMode()) {
                        $this->timerAfter($worker::onTimerGetMillisecond(), [
                            $this,
                            'runInSandbox'
                        ], function() use ($worker, $process) {
                            $worker::onTimerRun(new TimerParameter(0, $this->server, $this->container));
                        });
                    } else {
                        Timer::tick($worker::onTimerGetMillisecond(), function(int $timeId) use ($worker) {
                            $this->runInSandbox(function() use ($timeId, $worker) {
                                $worker::onTimerRun(new TimerParameter($timeId, $this->server, $this->container));
                            });
                        });
                    }
                }, false, 0, true));
            }
        }
    }
    
    
    /**
     * 阻塞计时器
     * @param int      $ms
     * @param callable $callback
     * @param mixed    ...$args
     */
    protected function timerAfter(int $ms, callable $callback, ...$args)
    {
        Timer::after($ms, function() use ($ms, $callback, $args) {
            $callback(...$args);
            
            $this->timerAfter($ms, $callback, $args);
        });
    }
}