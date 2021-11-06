<?php

namespace BusyPHP\swoole;

use BusyPHP\swoole\concerns\InteractsWithCoordinator;
use BusyPHP\swoole\concerns\InteractsWithGatewayServer;
use BusyPHP\swoole\concerns\InteractsWithHttp;
use BusyPHP\swoole\concerns\InteractsWithPools;
use BusyPHP\swoole\concerns\InteractsWithQueue;
use BusyPHP\swoole\concerns\InteractsWithRpcServer;
use BusyPHP\swoole\concerns\InteractsWithRpcClient;
use BusyPHP\swoole\concerns\InteractsWithServer;
use BusyPHP\swoole\concerns\InteractsWithSwooleTable;
use BusyPHP\swoole\concerns\InteractsWithTcp;
use BusyPHP\swoole\concerns\InteractsWithWebsocket;
use BusyPHP\swoole\concerns\WithApplication;
use BusyPHP\swoole\concerns\WithContainer;


/**
 * 服务管理类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/3 下午9:17 Manager.php $
 */
class Manager
{
    use InteractsWithCoordinator;
    use InteractsWithServer;
    use InteractsWithGatewayServer;
    use InteractsWithSwooleTable;
    use InteractsWithHttp;
    use InteractsWithWebsocket;
    use InteractsWithTcp;
    use InteractsWithPools;
    use InteractsWithRpcClient;
    use InteractsWithRpcServer;
    use InteractsWithQueue;
    use WithContainer;
    use WithApplication;
    
    /**
     * Server events.
     *
     * @var array
     */
    protected $events = [
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
        'request',
    ];
    
    
    /**
     * Initialize.
     */
    protected function initialize() : void
    {
        $this->prepareTables();
        $this->preparePools();
        $this->prepareGatewayServer();
        $this->prepareWebsocket();
        $this->setSwooleServerListeners();
        $this->prepareRpcServer();
        $this->prepareTcpServer();
        $this->prepareQueue();
        $this->prepareRpcClient();
    }
}