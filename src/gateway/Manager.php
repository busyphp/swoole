<?php

namespace BusyPHP\swoole\gateway;

use BusyPHP\App;
use BusyPHP\exception\ClassNotExtendsException;
use BusyPHP\exception\MethodNotFoundException;
use BusyPHP\swoole\concerns\InteractsWithCoordinator;
use BusyPHP\swoole\concerns\WithApplication;
use BusyPHP\swoole\concerns\WithContainer;
use BusyPHP\swoole\contract\BaseGateway;
use BusyPHP\swoole\Sandbox;
use Exception;
use RuntimeException;
use Swoole\Server;
use Swoole\Server\Port;
use think\Container;

/**
 * 网关服务管理类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/6 下午1:51 Manager.php $
 * @property App $app
 */
class Manager
{
    use WithContainer;
    use InteractsWithCoordinator;
    use WithApplication;
    
    /**
     * 准备TCP Server
     */
    protected function prepareGatewayServer()
    {
        $this->onEvent('workerStart', function(App $app) {
            $this->app = $app;
        });
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
        
        $this->prepareGatewayServer();
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
        $this->runInSandbox(function() use ($server, $fd, $reactorId) {
        }, Sandbox::createFd('gateway_', $fd, $reactorId, $server->worker_id), true);
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
        $this->runInSandbox(function() use ($server, $fd, $reactorId, $data) {
            $data   = (array) (@json_decode($data, true) ?: []);
            $secret = $data['secret'] ?? '';
            $class  = $data['class'] ?? '';
            $method = $data['method'] ?? '';
            $args   = $data['args'] ?? [];
            
            // 验证通信密钥
            try {
                if ($secret != Gateway::init()->getSecret()) {
                    throw new RuntimeException('Signature verification error');
                }
                
                if (!$class) {
                    throw new RuntimeException("Class does not exist");
                }
                
                if (!class_exists($class)) {
                    throw new RuntimeException('Class does not exist: ' . $class);
                }
                
                if (!$method) {
                    throw new RuntimeException("Method does not exist in class: {$class}");
                }
                
                $object = Container::getInstance()->make($class);
                if (!$object instanceof BaseGateway) {
                    throw new ClassNotExtendsException($object, BaseGateway::class);
                }
                if (!method_exists($object, $method)) {
                    throw new MethodNotFoundException($object, $method);
                }
                $object->setMust(true);
                $result = Container::getInstance()->invokeMethod([$object, $method], $args);
                $server->send($fd, json_encode(['status' => true, 'result' => $result]));
            } catch (Exception $e) {
                $server->send($fd, json_encode(['status' => false, 'message' => $e->getMessage()]));
            }
        }, Sandbox::createFd('gateway_', $fd, $reactorId, $server->worker_id), true);
    }
    
    
    /**
     * 连接被关闭
     * @param Server $server
     * @param int    $fd
     * @param int    $reactorId
     */
    public function onClose(Server $server, int $fd, int $reactorId)
    {
        $this->runInSandbox(function() use ($server, $fd, $reactorId) {
        }, Sandbox::createFd('gateway_', $fd, $reactorId, $server->worker_id));
    }
}