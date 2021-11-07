<?php

namespace BusyPHP\swoole\concerns;

use BusyPHP\App;
use BusyPHP\swoole\Job;
use BusyPHP\swoole\task\Job as TaskJob;
use Exception;
use Swoole\Process;
use Swoole\Runtime;
use Swoole\Server;
use Swoole\Server\Task;
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
}
