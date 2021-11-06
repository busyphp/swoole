<?php

namespace BusyPHP\swoole\concerns;

use BusyPHP\App;
use BusyPHP\helper\LogHelper;
use BusyPHP\swoole\gateway\Manager;
use Swoole\Server;
use Swoole\Server\Port;
use think\Container;

/**
 * Trait InteractsWithGateway
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/6 下午1:49 InteractsWithGatewayServer.php $
 * @property App       $app
 * @property Container $container
 * @method Server getServer()
 */
trait InteractsWithGatewayServer
{
    /**
     * 准备网关服务
     */
    protected function prepareGatewayServer()
    {
        $host = $this->getConfig('server.host', '') ?: '127.0.0.1';
        $port = $this->getConfig('gateway.server.port', '') ?: 8083;
        
        /** @var Port $server */
        $server = $this->getServer()->addlistener($host, $port, SWOOLE_SOCK_TCP);
        if (!$server) {
            LogHelper::default()->method(__METHOD__)->error("网关服务器启动失败, host: {$host}, port: {$port}");
            
            return;
        }
        
        /** @var Manager $manger */
        $manger = $this->container->make(Manager::class);
        $manger->attachToServer($server);
    }
}
