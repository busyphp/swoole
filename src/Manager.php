<?php

namespace BusyPHP\swoole;

use BusyPHP\App;
use BusyPHP\swoole\concerns\WithApplication;
use think\swoole\concerns\InteractsWithHttp;
use think\swoole\concerns\InteractsWithPools;
use think\swoole\concerns\InteractsWithRpcClient;
use think\swoole\concerns\InteractsWithRpcServer;
use think\swoole\concerns\InteractsWithServer;
use think\swoole\concerns\InteractsWithSwooleTable;
use think\swoole\concerns\InteractsWithWebsocket;
use think\swoole\PidManager;

class Manager
{
    use InteractsWithServer;
    use InteractsWithSwooleTable;
    use InteractsWithHttp;
    use InteractsWithWebsocket;
    use InteractsWithPools;
    use InteractsWithRpcClient;
    use InteractsWithRpcServer;
    use WithApplication;
    
    /**
     * @var App
     */
    protected $container;
    
    /** @var PidManager */
    protected $pidManager;
    
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
     * Manager constructor.
     * @param App        $container
     * @param PidManager $pidManager
     */
    public function __construct(App $container, PidManager $pidManager)
    {
        $this->container  = $container;
        $this->pidManager = $pidManager;
    }
    
    
    /**
     * Initialize.
     */
    protected function initialize() : void
    {
        $this->prepareTables();
        $this->preparePools();
        $this->prepareWebsocket();
        $this->setSwooleServerListeners();
        $this->prepareRpcServer();
        $this->prepareRpcClient();
    }
}