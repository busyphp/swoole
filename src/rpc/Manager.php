<?php

namespace BusyPHP\swoole\rpc;

use BusyPHP\App;
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
use BusyPHP\swoole\contract\rpc\ParserInterface;
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
    use WithContainer;
    use WithApplication;
    
    /**
     * Server events.
     *
     * @var array
     */
    protected $events    = [
        'start',
        'shutDown',
        'workerStart',
        'workerStop',
        'workerError',
        'workerExit',
        'packet',
        'task',
        'finish',
        'pipeMessage',
        'managerStart',
        'managerStop',
    ];
    
    protected $rpcEvents = [
        'connect',
        'receive',
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
        $services   = $this->getConfig('rpc.server.services', []);
        $middleware = $this->getConfig('rpc.server.middleware', []);
        
        $this->app->make(Dispatcher::class, [$services, $middleware]);
    }
    
    
    protected function bindRpcParser()
    {
        $parserClass = $this->getConfig('rpc.server.parser', JsonParser::class);
        
        $this->app->bind(ParserInterface::class, $parserClass);
        $this->app->make(ParserInterface::class);
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
                
                return $server->close($fd);
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
        }, $fd, true);
    }
    
    
    public function onReceive(Server $server, $fd, $reactorId, $data)
    {
        $this->recv($server, $fd, $data, function($data) use ($fd) {
            $this->runInSandbox(function(App $app, Dispatcher $dispatcher) use ($fd, $data) {
                $dispatcher->dispatch($app, $fd, $data);
            }, $fd, true);
        });
    }
    
    
    public function onClose(Server $server, int $fd, int $reactorId)
    {
        unset($this->channels[$fd]);
        $args = func_get_args();
        $this->runInSandbox(function(Event $event) use ($args) {
            $event->trigger('swoole.rpc.Close', $args);
        }, $fd);
    }
}
