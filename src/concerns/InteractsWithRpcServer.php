<?php

namespace BusyPHP\swoole\concerns;

use BusyPHP\App;
use BusyPHP\helper\LogHelper;
use Swoole\Server;
use BusyPHP\swoole\Pool;
use BusyPHP\swoole\rpc\Manager;

/**
 * Trait InteractsWithRpc
 * @package BusyPHP\swoole\concerns
 * @property App $app
 * @property App $container
 * @method Server getServer()
 * @method Pool getPools()
 */
trait InteractsWithRpcServer
{
    protected function prepareRpcServer()
    {
        if ($this->getConfig('rpc.server.enable', false)) {
            $host = $this->getConfig('server.host', '') ?: '127.0.0.1';
            $port = $this->getConfig('rpc.server.port', '') ?: 8082;
            
            $rpcServer = $this->getServer()->addlistener($host, $port, SWOOLE_SOCK_TCP);
            if (!$rpcServer) {
                LogHelper::default()->method(__METHOD__)->error("RPC服务器启动失败, host: {$host}, port: {$port}");
                
                return;
            }
            
            /** @var Manager $rpcManager */
            $rpcManager = $this->container->make(Manager::class);
            
            $rpcManager->attachToServer($rpcServer);
        }
    }
}
