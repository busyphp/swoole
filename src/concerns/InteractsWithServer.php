<?php

namespace BusyPHP\swoole\concerns;

use BusyPHP\App;
use BusyPHP\exception\ClassNotFoundException;
use BusyPHP\exception\ClassNotImplementsException;
use BusyPHP\helper\ArrayHelper;
use BusyPHP\swoole\contract\task\TaskWorkerInterface;
use BusyPHP\swoole\Job;
use BusyPHP\swoole\task\Job as TaskJob;
use BusyPHP\swoole\task\parameter\FinishParameter;
use BusyPHP\swoole\task\parameter\TimerParameter;
use DomainException;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use Swoole\Process;
use Swoole\Runtime;
use Swoole\Server;
use Swoole\Server\Task;
use think\Collection;
use think\Event;
use think\helper\Str;
use BusyPHP\swoole\FileWatcher;

/**
 * Trait InteractsWithServer
 * @package BusyPHP\swoole\concerns
 * @property App $container
 */
trait InteractsWithServer
{
    /**
     * 启动服务
     */
    public function run() : void
    {
        $this->getServer()->set([
            'task_enable_coroutine' => true,
            'send_yield'            => true,
            'reload_async'          => true,
            'enable_coroutine'      => true,
            'max_request'           => 0,
            'task_max_request'      => 0,
        ]);
        $this->initialize();
        $this->triggerEvent('init');
        
        //热更新
        if ($this->getConfig('hot_update.enable', false)) {
            $this->addHotUpdateProcess();
        }
        
        $this->getServer()->start();
    }
    
    
    /**
     * 停止服务
     */
    public function stop() : void
    {
        $this->getServer()->shutdown();
    }
    
    
    /**
     * 服务已启动事件
     */
    public function onStart()
    {
        $this->setProcessName('master process');
        
        $this->triggerEvent('start', func_get_args());
    }
    
    
    /**
     * Manager进程已启动事件
     */
    public function onManagerStart()
    {
        $this->setProcessName('manager process');
        $this->triggerEvent('managerStart', func_get_args());
    }
    
    
    /**
     * Worker已启动事件
     * @param \Swoole\Http\Server|mixed $server
     * @throws Exception
     */
    public function onWorkerStart($server)
    {
        $this->resumeCoordinator('workerStart', function() use ($server) {
            Runtime::enableCoroutine($this->getConfig('coroutine.enable', true), $this->getConfig('coroutine.flags', SWOOLE_HOOK_ALL));
            
            $this->clearCache();
            $this->setProcessName($server->taskworker ? 'task process' : 'worker process');
            $this->prepareApplication();
            $this->bindServer();
            $this->prepareTask();
            $this->triggerEvent('workerStart', $this->app);
        });
    }
    
    
    /**
     * 任务执行事件
     * @param mixed $server
     * @param Task  $task
     */
    public function onTask($server, Task $task)
    {
        $this->runInSandbox(function(Event $event, App $app) use ($task) {
            if ($task->data instanceof Job) {
                $task->data->run($app);
            } elseif ($task->data instanceof TaskJob) {
                $task->data->run($app, $this->getServer(), $task);
            } else {
                $event->trigger('swoole.task', $task);
            }
        }, $task->id);
    }
    
    
    /**
     * 服务已关闭事件
     */
    public function onShutdown()
    {
        $this->triggerEvent('shutdown');
    }
    
    
    /**
     * 绑定Swoole服务对象
     */
    protected function bindServer()
    {
        $this->app->bind(Server::class, $this->getServer());
        $this->app->bind('swoole.server', Server::class);
    }
    
    
    /**
     * 获取Swoole服务对象
     * @return Server
     */
    public function getServer()
    {
        return $this->container->make(Server::class);
    }
    
    
    /**
     * 设置启动监听
     */
    protected function setSwooleServerListeners()
    {
        foreach ($this->events as $event) {
            $listener = Str::camel("on_$event");
            $callback = method_exists($this, $listener) ? [$this, $listener] : function() use ($event) {
                $this->triggerEvent($event, func_get_args());
            };
            
            $this->getServer()->on($event, $callback);
        }
    }
    
    
    /**
     * 热更新
     */
    protected function addHotUpdateProcess()
    {
        $process = new Process(function() {
            $watcher = new FileWatcher($this->getConfig('hot_update.include', []), $this->getConfig('hot_update.exclude', []), $this->getConfig('hot_update.name', []));
            
            $watcher->watch(function() {
                $this->getServer()->reload();
            });
        }, false, 0, true);
        
        $this->addProcess($process);
    }
    
    
    /**
     * 添加一个进程到server中
     * @param Process $process
     */
    public function addProcess(Process $process) : void
    {
        $this->getServer()->addProcess($process);
    }
    
    
    /**
     * 清除apc、op缓存
     */
    protected function clearCache()
    {
        if (extension_loaded('apc')) {
            apc_clear_cache();
        }
        
        if (extension_loaded('Zend OPcache')) {
            opcache_reset();
        }
    }
    
    
    /**
     * 设置进程名称
     * @param $process
     */
    protected function setProcessName($process)
    {
        $serverName = 'swoole server';
        $appName    = $this->container->config->get('app.name', 'BusyPHP');
        
        $name = sprintf('%s: %s for %s', $serverName, $process, $appName);
        
        @cli_set_process_title($name);
    }
    
    
    /**
     * 准备任务处理
     */
    protected function prepareTask()
    {
        if (!$this->getConfig('task.enable', false) || $this->getServer()->taskworker) {
            return;
        }
        
        $workers = $this->getConfig('task.workers');
        if (is_callable($workers)) {
            $workers = call_user_func($workers);
        }
        $workers = is_array($workers) ? $workers : [];
        foreach ($workers as $worker) {
            if (!$worker) {
                continue;
            }
            
            $this->startTaskTimer($worker);
        }
    }
    
    
    /**
     * 启动任务定时器
     * @param string $worker
     */
    protected function startTaskTimer(string $worker)
    {
        if (!class_exists($worker)) {
            throw new ClassNotFoundException($worker);
        }
        
        if (!is_subclass_of($worker, TaskWorkerInterface::class)) {
            throw new ClassNotImplementsException($worker, TaskWorkerInterface::class);
        }
        
        $interval = call_user_func([$worker, 'getTimerIntervalMs']);
        if ($interval < 0) {
            throw new RuntimeException('the interval must be greater than 0 milliseconds');
        }
        
        $this->getServer()->tick($interval, function($timeId) use ($worker) {
            $this->runInSandbox(function(App $app, Server $server) use ($worker, $timeId) {
                $this->runTask($app, $server, $worker, $timeId);
            }, "task_time_{$timeId}");
        });
    }
    
    
    /**
     * 执行任务
     * @param App    $app
     * @param Server $server
     * @param string $worker
     * @param int    $timeId
     */
    protected function runTask(App $app, Server $server, string $worker, int $timeId)
    {
        if (!is_subclass_of($worker, TaskWorkerInterface::class)) {
            throw new ClassNotImplementsException($worker, TaskWorkerInterface::class);
        }
        
        $emptyIdle  = call_user_func([$worker, 'getTaskEmptyIdleStatus']);
        $maxTasking = call_user_func([$worker, 'getTaskMaxNumber']);
        $stats      = $server->stats();
        
        // 没有空闲进程不投递
        if (!$emptyIdle && intval($stats['task_idle_worker_num'] ?? 0) == 0) {
            return;
        }
        
        // 排队进程超出设置则不允许投递
        if ($maxTasking > 0 && intval($stats['tasking_num'] ?? 0) > $maxTasking) {
            return;
        }
        
        // Worker 进程忙碌中
        // Swoole 版本 >= v4.5.0RC1 可用
        if (method_exists($server, 'getWorkerStatus') && $server->getWorkerStatus() === SWOOLE_WORKER_BUSY) {
            return;
        }
        
        $parameter = new TimerParameter($timeId, $server, $app);
        call_user_func_array([$worker, 'onTimer'], [$parameter]);
        
        // 不需要投递到task中
        if (!$parameter->isDeliver() || empty($parameter->getData())) {
            return;
        }
        
        // 异步任务
        if ($parameter->isAsync()) {
            $server->task(new TaskJob($worker, $parameter->getData()), $parameter->getDstWorkerId(), function(Server $server, int $taskId, $finishData) use ($worker, $parameter) {
                $this->runInSandbox(function(App $app, Server $server) use ($parameter, $finishData, $taskId, $worker) {
                    call_user_func_array([
                        $worker,
                        'onFinish'
                    ], [new FinishParameter($app, $server, $parameter->getData(), $finishData, $taskId)]);
                }, "task_finish_{$taskId}");
            });
        } else {
            // 同步并发任务
            if ($parameter->isMulti()) {
                $data = $parameter->getData();
                if (!$data instanceof Collection) {
                    if (!is_array($data) || ArrayHelper::isAssoc($data)) {
                        throw new InvalidArgumentException('Deliver data must be an numeric index array or be an class think\Collection');
                    }
                }
                
                if (count($data) > 1024) {
                    throw new DomainException('The maximum concurrent tasks must not exceed 1024');
                }
                
                $tasks = [];
                foreach ($data as $item) {
                    $tasks[] = new TaskJob($worker, $item);
                }
                
                $results = $server->taskCo($tasks, $parameter->getTimeout());
                if ($data instanceof Collection) {
                    $results = Collection::make($results);
                }
                call_user_func_array([
                    $worker,
                    'onFinish'
                ], [new FinishParameter($app, $server, $data, $results)]);
            }
            
            //
            // 同步任务
            else {
                $results = $server->taskwait(new TaskJob($worker, $parameter->getData()), $parameter->getTimeout(), $parameter->getDstWorkerId());
                
                call_user_func_array([
                    $worker,
                    'onFinish'
                ], [new FinishParameter($app, $server, $parameter->getData(), $results, $parameter->getDstWorkerId())]);
            }
        }
    }
}
