<?php

namespace BusyPHP\swoole\concerns;

use BusyPHP\App;
use BusyPHP\Request;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Websocket\Frame;
use Swoole\Websocket\Server;
use think\Container;
use think\helper\Str;
use BusyPHP\swoole\contract\websocket\WebsocketRoomInterface;
use BusyPHP\swoole\Middleware;
use BusyPHP\swoole\Websocket;
use BusyPHP\swoole\websocket\Room;

/**
 * WebSocket服务类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/6 下午9:31 InteractsWithWebsocket.php $
 * @property App       $app
 * @property Container $container
 * @method \Swoole\Server getServer()
 */
trait InteractsWithWebsocket
{
    /**
     * @var boolean
     */
    protected $isWebsocketServer = false;
    
    /**
     * @var WebsocketRoomInterface
     */
    protected $websocketRoom;
    
    /**
     * Websocket server events.
     *
     * @var array
     */
    protected $wsEvents = [
        /**
         * Websocket已连接
         * @see InteractsWithWebsocket::onOpen()
         */
        'open',
        
        /**
         * 收到Websocket消息
         * @see InteractsWithWebsocket::onMessage()
         */
        'message',
        
        /**
         * Websocket已断开
         * @see InteractsWithWebsocket::onClose()
         */
        'close'
    ];
    
    
    /**
     * "onOpen" listener.
     *
     * @param Server        $server
     * @param SwooleRequest $req
     */
    public function onOpen($server, $req)
    {
        $this->waitCoordinator('workerStart');
        
        $this->runInSandbox(function(App $app, Websocket $websocket) use ($req) {
            $request = $this->prepareRequest($req);
            $app->instance('request', $request);
            $request = $this->setRequestThroughMiddleware($app, $request);
            $websocket->setSender($req->fd);
            $websocket->onOpen($req->fd, $request);
        });
    }
    
    
    /**
     * "onMessage" listener.
     *
     * @param Server $server
     * @param Frame  $frame
     */
    public function onMessage($server, $frame)
    {
        $this->runInSandbox(function(Websocket $websocket) use ($frame) {
            $websocket->setSender($frame->fd);
            $websocket->onMessage($frame);
        });
    }
    
    
    /**
     * "onClose" listener.
     *
     * @param Server $server
     * @param int    $fd
     * @param int    $reactorId
     */
    public function onClose($server, $fd, $reactorId)
    {
        if (!$server instanceof Server || !$this->isWebsocketServer($fd)) {
            return;
        }
        
        $this->runInSandbox(function(Websocket $websocket) use ($fd, $reactorId) {
            $websocket->setSender($fd);
            try {
                $websocket->onClose($fd, $reactorId);
            } finally {
                // leave all rooms
                $websocket->leave();
            }
        });
    }
    
    
    /**
     * @param App     $app
     * @param Request $request
     * @return Request
     */
    protected function setRequestThroughMiddleware(App $app, Request $request) : Request
    {
        return Middleware::make($app, $this->getSwooleConfig('websocket.server.middleware', []))
            ->pipeline()
            ->send($request)
            ->then(function($request) {
                return $request;
            });
    }
    
    
    /**
     * Prepare settings if websocket is enabled.
     */
    protected function prepareWebsocket()
    {
        if (!$this->isWebsocketServer = $this->getSwooleConfig('websocket.server.enable', false)) {
            return;
        }
        
        $this->events = array_merge($this->events ?? [], $this->wsEvents);
        
        $this->prepareWebsocketRoom();
        
        $this->onEvent('workerStart', function() {
            $this->bindWebsocketRoom();
            $this->bindWebsocketHandler();
            $this->prepareWebsocketListener();
        });
    }
    
    
    /**
     * Check if it's a websocket fd.
     *
     * @param int $fd
     *
     * @return bool
     */
    protected function isWebsocketServer(int $fd) : bool
    {
        return $this->getServer()->getClientInfo($fd)['websocket_status'] ?? false;
    }
    
    
    /**
     * Prepare websocket room.
     */
    protected function prepareWebsocketRoom()
    {
        // create room instance and initialize
        $this->websocketRoom = $this->container->make(Room::class);
        $this->websocketRoom->prepare();
    }
    
    
    protected function prepareWebsocketListener()
    {
        $listeners = $this->getSwooleConfig('websocket.server.listen', []);
        
        foreach ($listeners as $event => $listener) {
            $this->app->event->listen('swoole.websocket.' . Str::studly($event), $listener);
        }
        
        $subscribers = $this->getSwooleConfig('websocket.server.subscribe', []);
        
        foreach ($subscribers as $subscriber) {
            $this->app->event->observe($subscriber, 'swoole.websocket.');
        }
    }
    
    
    /**
     * Prepare websocket handler for onOpen and onClose callback
     */
    protected function bindWebsocketHandler()
    {
        $handlerClass = $this->getSwooleConfig('websocket.server.handler');
        if ($handlerClass && is_subclass_of($handlerClass, Websocket::class)) {
            $this->app->bind(Websocket::class, $handlerClass);
        }
    }
    
    
    /**
     * Bind room instance to app container.
     */
    protected function bindWebsocketRoom() : void
    {
        $this->app->instance(Room::class, $this->websocketRoom);
    }
}
