<?php

namespace BusyPHP\swoole\rpc;

use BusyPHP\App;
use BusyPHP\swoole\concerns\InteractsWithWebSocketClient;
use BusyPHP\swoole\concerns\WithSwooleConfig;
use Swoole\Coroutine;
use Swoole\Server;
use Swoole\Server\Port;
use think\Event;
use think\helper\Str;
use BusyPHP\swoole\concerns\InteractsWithCoordinator;
use BusyPHP\swoole\concerns\InteractsWithPools;
use BusyPHP\swoole\concerns\InteractsWithQueue;
use BusyPHP\swoole\concerns\InteractsWithRpcClient;
use BusyPHP\swoole\concerns\InteractsWithServer;
use BusyPHP\swoole\concerns\InteractsWithSwooleTable;
use BusyPHP\swoole\concerns\WithApplication;
use BusyPHP\swoole\concerns\WithContainer;
use BusyPHP\swoole\contract\rpc\RpcParserInterface;
use BusyPHP\swoole\rpc\server\Channel;
use BusyPHP\swoole\rpc\server\Dispatcher;
use Throwable;

/**
 * RPC管理类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/5 上午10:18 Manager.php $
 */
class Manager
{
    use InteractsWithCoordinator;
    use InteractsWithServer;
    use InteractsWithSwooleTable;
    use InteractsWithPools;
    use InteractsWithRpcClient;
    use InteractsWithQueue;
    use InteractsWithWebSocketClient;
    use WithContainer;
    use WithApplication;
    use WithSwooleConfig;
    
    /**
     * Server events.
     *
     * @var array
     */
    protected $events    = [
        /**
         * 启动后在主进程（master）的主线程回调此函数
         * @see InteractsWithServer::onStart()
         */
        'start',
        
        /**
         * 此事件在 Server 正常结束时发生
         * @see InteractsWithServer::onShutdown()
         */
        'shutdown',
        
        /**
         * Worker启动事件
         * 此事件在 Worker 进程 / Task 进程 启动时发生，这里创建的对象可以在进程生命周期内使用
         * @see InteractsWithServer::onWorkerStart()
         */
        'workerStart',
        
        /**
         * 此事件在 Worker 进程终止时发生。在此函数中可以回收 Worker 进程申请的各类资源
         */
        'workerStop',
        
        /**
         * 当 Worker/Task 进程发生异常后会在 Manager 进程内回调此函数
         * 此函数主要用于报警和监控，一旦发现 Worker 进程异常退出，那么很有可能是遇到了致命错误或者进程 Core Dump。通过记录日志或者发送报警的信息来提示开发者进行相应的处理。
         */
        'workerError',
        
        /**
         * 进程退出事件
         * 仅在开启 reload_async 特性后有效。参见 如何正确的重启服务
         */
        'workerExit',
        
        /**
         * 接收到 UDP 数据包时回调此函数，发生在 worker 进程中
         */
        'packet',
        
        /**
         * 在 task 进程内被调用。worker 进程可以使用 task 函数向 task_worker 进程投递新的任务。当前的 Task 进程在调用 onTask 回调函数时会将进程状态切换为忙碌，这时将不再接收新的 Task，当 onTask 函数返回时会将进程状态切换为空闲然后继续接收新的 Task。
         * @see InteractsWithServer::onTask()
         */
        'task',
        
        /**
         * 处理异步任务的结果，此回调函数在worker进程中执行
         */
        'finish',
        
        /**
         * 当工作进程收到由 {@see Server::sendMessage()} 发送的 unixSocket 消息时会触发
         */
        'pipeMessage',
        
        /**
         * 当管理进程启动时触发此事件
         * @see InteractsWithServer::onManagerStart()
         */
        'managerStart',
        
        /**
         * 当管理进程结束时触发
         */
        'managerStop',
    ];
    
    protected $rpcEvents = [
        /**
         * @see Manager::onConnect()
         */
        'connect',
        
        /**
         * @see Manager::onReceive()
         */
        'receive',
        
        /**
         * @see Manager::onClose()
         */
        'close',
    ];
    
    /** @var Channel[] */
    protected $channels = [];
    
    
    /**
     * Initialize.
     */
    protected function initialize() : void
    {
        $this->events = array_merge($this->events ?? [], $this->rpcEvents);
        $this->prepareTables();
        $this->preparePools();
        $this->setSwooleServerListeners();
        $this->prepareRpcServer();
        $this->prepareQueue();
        $this->prepareWebSocketClient();
        $this->prepareRpcClient();
    }
    
    
    protected function prepareRpcServer()
    {
        $this->onEvent('workerStart', function() {
            $this->bindRpcParser();
            $this->bindRpcDispatcher();
        });
    }
    
    
    public function attachToServer(Port $port)
    {
        $port->set([]);
        foreach ($this->rpcEvents as $event) {
            $listener = Str::camel("on_$event");
            $callback = method_exists($this, $listener) ? [$this, $listener] : function() use ($event) {
                $this->triggerEvent('rpc.' . $event, func_get_args());
            };
            
            $port->on($event, $callback);
        }
        
        $this->onEvent('workerStart', function(App $app) {
            $this->app = $app;
        });
        $this->prepareRpcServer();
    }
    
    
    protected function bindRpcDispatcher()
    {
        $services   = $this->getSwooleConfig('rpc.server.services', []);
        $middleware = $this->getSwooleConfig('rpc.server.middleware', []);
        
        $this->app->make(Dispatcher::class, [$services, $middleware]);
    }
    
    
    protected function bindRpcParser()
    {
        $parserClass = $this->getSwooleConfig('rpc.server.parser', JsonRpcParser::class);
        
        $this->app->bind(RpcParserInterface::class, $parserClass);
        $this->app->make(RpcParserInterface::class);
    }
    
    
    protected function recv(Server $server, $fd, $data, $callback)
    {
        if (!isset($this->channels[$fd]) || empty($handler = $this->channels[$fd]->pop())) {
            //解析包头
            try {
                [$handler, $data] = Packer::unpack($data);
                
                $this->channels[$fd] = new Channel($handler);
            } catch (Throwable $e) {
                //错误的包头
                Coroutine::create($callback, Error::make(Dispatcher::INVALID_REQUEST, $e->getMessage()));
                
                $server->close($fd);
                
                return;
            }
            
            $handler = $this->channels[$fd]->pop();
        }
        
        $result = $handler->write($data);
        
        if (!empty($result)) {
            Coroutine::create($callback, $result);
            $this->channels[$fd]->close();
        } else {
            $this->channels[$fd]->push($handler);
        }
        
        if (!empty($data)) {
            $this->recv($server, $fd, $data, $callback);
        }
    }
    
    
    public function onConnect(Server $server, int $fd, int $reactorId)
    {
        $this->waitCoordinator('workerStart');
        //TODO 保证onConnect onReceive onClose 执行顺序
        $args = func_get_args();
        $this->runInSandbox(function(Event $event) use ($args) {
            $event->trigger('swoole.rpc.Connect', $args);
        });
    }
    
    
    public function onReceive(Server $server, $fd, $reactorId, $data)
    {
        $this->recv($server, $fd, $data, function($data) use ($fd) {
            $this->runInSandbox(function(App $app, Dispatcher $dispatcher) use ($fd, $data) {
                $dispatcher->dispatch($app, $fd, $data);
            });
        });
    }
    
    
    public function onClose(Server $server, int $fd, int $reactorId)
    {
        unset($this->channels[$fd]);
        $args = func_get_args();
        $this->runInSandbox(function(Event $event) use ($args) {
            $event->trigger('swoole.rpc.Close', $args);
        });
    }
}
