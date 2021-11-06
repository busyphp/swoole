<?php

namespace BusyPHP\swoole\concerns;

use BusyPHP\App;
use BusyPHP\helper\LogHelper;
use BusyPHP\swoole\tcp\Manager;
use Swoole\Server;
use Swoole\Server\Port;
use think\Container;


/**
 * 准备TCP服务
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/5 上午10:08 InteractsWithTcp.php $
 * @property App       $app
 * @property Container $container
 * @method Server getServer()
 */
trait InteractsWithTcp
{
    /**
     * 准备TCP服务器
     */
    protected function prepareTcpServer()
    {
        if (!$this->getConfig('tcp.server.enable', false)) {
            return;
        }
        
        $host = $this->getConfig('server.host', '') ?: '127.0.0.1';
        $port = $this->getConfig('tcp.server.port', '') ?: 8081;
        
        /** @var Port $tcpServer */
        $tcpServer = $this->getServer()->addlistener($host, $port, SWOOLE_SOCK_TCP);
        if (!$tcpServer) {
            LogHelper::default()->method(__METHOD__)->error("TCP服务器启动失败, host: {$host}, port: {$port}");
            
            return;
        }
        
        /** @var Manager $tcpManager */
        $tcpManager = $this->container->make(Manager::class);
        $tcpManager->attachToServer($tcpServer);
    }
}
