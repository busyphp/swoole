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
use Swoole\Server;


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
        
        /**
         * Http请求事件
         * @see InteractsWithHttp::onRequest()
         */
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