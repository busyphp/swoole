<?php

namespace BusyPHP\swoole\concerns;

use BusyPHP\App;
use BusyPHP\exception\ClassNotImplementsException;
use BusyPHP\helper\ArrayHelper;
use BusyPHP\swoole\contract\task\TaskInterface;
use BusyPHP\swoole\event\TaskEvent;
use BusyPHP\swoole\Job;
use BusyPHP\swoole\task\Job as TaskJob;
use BusyPHP\swoole\contract\task\FinishParameter;
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
 * 服务类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/6 下午9:13 InteractsWithServer.php $
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
            'enable_coroutine'      => true,
        ]);
        $this->initialize();
        $this->triggerEvent('init');
        
        //热更新
        if ($this->getSwooleConfig('hot_update.enable', false)) {
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
            // 启用协程
            Runtime::enableCoroutine();
            
            $this->clearCache();
            $this->setProcessName($server->taskworker ? 'task process' : 'worker process');
            $this->prepareApplication();
            $this->bindServer();
            $this->triggerEvent('workerStart', $this->app);
        });
    }
    
    
    /**
     * 任务执行事件
     * @param mixed $server
     * @param Task  $task
     */
    public function onTask(Server $server, Task $task)
    {
        $this->runInSandbox(function(Event $event, App $app) use ($task, $server) {
            if ($task->data instanceof Job) {
                return $task->data->run($app);
            } elseif ($task->data instanceof TaskJob) {
                return $task->data->run($app, $server, $task);
            } else {
                $taskEvent         = new TaskEvent();
                $taskEvent->task   = $task;
                $taskEvent->server = $server;
                
                return $event->trigger($taskEvent);
            }
        });
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
    public function getServer() : Server
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
            $watcher = new FileWatcher($this->getSwooleConfig('hot_update.include', []), $this->getSwooleConfig('hot_update.exclude', []), $this->getSwooleConfig('hot_update.name', []));
            
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
     * 检测是否可以使用任务
     * @return bool
     */
    public function taskStatus() : bool
    {
        $server = $this->getServer();
        if ($server->taskworker) {
            return false;
        }
        
        $stats = $server->stats();
        if (($stats['task_idle_worker_num'] ?? 0) == 0) {
            return false;
        }
        
        if (($stats['tasking_num'] ?? 0) > 0) {
            return false;
        }
        
        // Worker 进程忙碌中
        // Swoole 版本 >= v4.5.0RC1 可用
        if (method_exists($server, 'getWorkerStatus') && $server->getWorkerStatus() === SWOOLE_WORKER_BUSY) {
            return false;
        }
        
        return true;
    }
    
    
    /**
     * 执行异步任务
     * @param string   $worker 任务类
     * @param mixed    $data 任务数据
     * @param int|null $dstWorkerId 希望投递到哪个worker中
     */
    public function taskAsync(string $worker, $data, ?int $dstWorkerId = null)
    {
        if (!is_subclass_of($worker, TaskInterface::class)) {
            throw new ClassNotImplementsException($worker, TaskInterface::class);
        }
        
        if (!$this->taskStatus()) {
            throw new RuntimeException("There are no idle processes or task processes busy");
        }
        
        $this->getServer()
            ->task(new TaskJob($worker, $data), $dstWorkerId, function(Server $server, int $taskId, $finishData) use ($worker, $data) {
                $this->runInSandbox(function(App $app, Server $server) use ($data, $finishData, $taskId, $worker) {
                    $worker::onTaskFinish(new FinishParameter($app, $server, $data, $finishData, $taskId));
                });
            });
    }
    
    
    /**
     * 执行同步任务
     * @param string     $worker 任务类
     * @param mixed      $data 任务数据
     * @param float|null $timeout 任务超时秒，可以精确到0.001秒
     * @param int|null   $dstWorkerId 希望投递到哪个worker中
     */
    public function taskSync(string $worker, $data, ?float $timeout = null, ?int $dstWorkerId = null)
    {
        if (!is_subclass_of($worker, TaskInterface::class)) {
            throw new ClassNotImplementsException($worker, TaskInterface::class);
        }
        
        if (!$this->taskStatus()) {
            throw new RuntimeException("There are no idle processes or task processes busy");
        }
        
        // 同步任务
        $server  = $this->getServer();
        $results = $server->taskwait(new TaskJob($worker, $data), $timeout, $dstWorkerId);
        $worker::onTaskFinish(new FinishParameter($this->app, $server, $data, $results));
    }
    
    
    /**
     * 执行同步等待并发任务
     * @param string     $worker
     * @param mixed      $data
     * @param float|null $timeout
     */
    public function taskSyncMulti(string $worker, $data, ?float $timeout = null)
    {
        if (!is_subclass_of($worker, TaskInterface::class)) {
            throw new ClassNotImplementsException($worker, TaskInterface::class);
        }
        
        if (!$this->taskStatus()) {
            throw new RuntimeException("There are no idle processes or task processes busy");
        }
        
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
        
        $server  = $this->getServer();
        $results = $server->taskCo($tasks, $timeout);
        if ($data instanceof Collection) {
            $results = Collection::make($results);
        }
        $worker::onTaskFinish(new FinishParameter($this->app, $server, $data, $results));
    }
}
