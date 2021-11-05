<?php

namespace BusyPHP\swoole\tcp;

use BusyPHP\App;
use BusyPHP\swoole\concerns\InteractsWithCoordinator;
use BusyPHP\swoole\concerns\WithApplication;
use BusyPHP\swoole\concerns\WithContainer;
use BusyPHP\swoole\contract\tcp\TcpHandleInterface;
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
            $handler         = $this->getConfig('tcp.handler', '') ?: TcpHandle::class;
            $this->excludeIp = $this->getConfig('tcp.exclude_ip', []);
            
            $this->app = $app;
            $this->app->bind(TcpHandleInterface::class, $handler);
            $this->app->make(TcpHandleInterface::class);
        });
    }
    
    
    /**
     * 获取TCP事件对象
     * @return TcpHandleInterface
     */
    protected function getHandle() : TcpHandleInterface
    {
        return $this->app->make(TcpHandleInterface::class);
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
            // 过滤客户端
            if ($this->checkFilter($server, $fd, $reactorId)) {
                return;
            }
            
            
            if ($this->getHandle()->onConnect($server, $fd, $reactorId)) {
                return;
            }
            
            $this->triggerEvent('tcp.connect', $args);
        }, $fd, true);
    }
    
    
    /**
     * 接收到数据
     * @param Server $server
     * @param        $fd
     * @param        $reactorId
     * @param        $data
     */
    public function onReceive(Server $server, $fd, $reactorId, $data)
    {
        $args = func_get_args();
        $this->runInSandbox(function() use ($server, $fd, $reactorId, $data, $args) {
            // 网关入口
            if (0 === strpos($data, TcpGateway::$prefix)) {
                $data    = substr($data, strlen(TcpGateway::$prefix));
                $data    = explode(',', $data);
                $time    = intval($data[0] ?? 0);
                $sign    = $data[1] ?? '';
                $content = $data[2] ?? '';
                if (!TcpGateway::verify($sign, $content, $time)) {
                    $server->send($fd, 'Signature verification error');
                    
                    return;
                }
                $content  = base64_decode($content);
                $content  = json_decode((string) $content, true) ?: [];
                $clientId = $content['client_id'] ?? '';
                $sendData = (string) ($content['data'] ?? '');
                if (!$clientId) {
                    $server->send($fd, 'Data exception: client_id');
                    
                    return;
                }
                
                if ($server->exists($clientId)) {
                    $server->send($clientId, $sendData);
                    $server->send($fd, 'success');
                } else {
                    $server->send($fd, 'Client does not exist: ' . $clientId);
                }
                
                return;
            }
            
            
            if ($this->getHandle()->onReceive($server, $fd, $reactorId, $data)) {
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
            // 过滤客户端
            if ($this->checkFilter($server, $fd, $reactorId)) {
                return;
            }
            
            if ($this->getHandle()->onClose($server, $fd, $reactorId)) {
                return;
            }
            
            $this->triggerEvent('tcp.close', $args);
        }, $fd);
    }
    
    
    /**
     * 检测是否要过滤的客户端
     * @param Server $server
     * @param int    $fd
     * @param int    $reactorId
     * @return bool
     */
    public function checkFilter(Server $server, int $fd, int $reactorId) : bool
    {
        $clientInfo = $server->getClientInfo($fd, $reactorId);
        
        return in_array(trim((string) $clientInfo['remote_ip'] ?? ''), $this->excludeIp);
    }
}