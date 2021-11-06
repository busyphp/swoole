<?php

namespace BusyPHP\swoole\tcp;

use BusyPHP\App;
use BusyPHP\swoole\concerns\InteractsWithCoordinator;
use BusyPHP\swoole\concerns\WithApplication;
use BusyPHP\swoole\concerns\WithContainer;
use BusyPHP\swoole\contract\tcp\TcpHandlerInterface;
use BusyPHP\swoole\tcp\handler\TcpHandler;
use Swoole\Server;
use Swoole\Server\Port;

/**
 * TCP管理类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/5 上午8:23 Manager.php $
 * @property App $app
 */
class Manager
{
    use WithContainer;
    use InteractsWithCoordinator;
    use WithApplication;
    
    /**
     * 排除网关IP
     * @var array
     */
    protected $excludeIp = [];
    
    
    /**
     * 准备TCP Server
     */
    protected function prepareTcpServer()
    {
        $this->onEvent('workerStart', function(App $app) {
            $handler         = $this->getConfig('tcp.handler', '') ?: TcpHandler::class;
            $this->excludeIp = $this->getConfig('tcp.gateway.exclude_ip', []);
            
            $this->app = $app;
            $this->app->bind(TcpHandlerInterface::class, $handler);
            $this->app->make(TcpHandlerInterface::class);
        });
    }
    
    
    /**
     * 获取TCP事件对象
     * @return TcpHandlerInterface
     */
    protected function getHandle() : TcpHandlerInterface
    {
        return $this->app->make(TcpHandlerInterface::class);
    }
    
    
    /**
     * 绑定Server
     * @param Port $port
     */
    public function attachToServer(Port $port)
    {
        $port->set([]);
        $port->on('connect', [$this, 'onConnect']);
        $port->on('receive', [$this, 'onReceive']);
        $port->on('close', [$this, 'onClose']);
        
        $this->prepareTcpServer();
    }
    
    
    /**
     * 已连接
     * @param Server $server
     * @param int    $fd
     * @param int    $reactorId
     */
    public function onConnect(Server $server, int $fd, int $reactorId)
    {
        $this->waitCoordinator('workerStart');
        
        $args = func_get_args();
        $this->runInSandbox(function() use ($server, $fd, $reactorId, $args) {
            if (false === $this->getHandle()->onConnect($server, $fd, $reactorId)) {
                return;
            }
            
            $this->triggerEvent('tcp.connect', $args);
        }, $fd, true);
    }
    
    
    /**
     * 接收到数据
     * @param Server $server
     * @param int    $fd
     * @param int    $reactorId
     * @param mixed  $data
     */
    public function onReceive(Server $server, int $fd, int $reactorId, $data)
    {
        $args = func_get_args();
        $this->runInSandbox(function() use ($server, $fd, $reactorId, $data, $args) {
            if (false === $this->getHandle()->onReceive($server, $fd, $reactorId, $data)) {
                return;
            }
            
            $this->triggerEvent("tcp.receive", $args);
        }, $fd, true);
    }
    
    
    /**
     * 连接被关闭
     * @param Server $server
     * @param int    $fd
     * @param int    $reactorId
     */
    public function onClose(Server $server, int $fd, int $reactorId)
    {
        $args = func_get_args();
        $this->runInSandbox(function() use ($server, $fd, $reactorId, $args) {
            if (false === $this->getHandle()->onClose($server, $fd, $reactorId)) {
                return;
            }
            
            $this->triggerEvent('tcp.close', $args);
        }, $fd);
    }
}