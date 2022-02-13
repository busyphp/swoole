<?php

namespace BusyPHP\swoole\concerns;

use BusyPHP\App;
use Smf\ConnectionPool\ConnectionPool;
use Swoole\Server;
use BusyPHP\swoole\Pool;
use BusyPHP\swoole\pool\Client;
use BusyPHP\swoole\rpc\client\Connector;
use BusyPHP\swoole\rpc\client\RpcGateway;
use BusyPHP\swoole\rpc\client\Proxy;
use BusyPHP\swoole\rpc\JsonRpcParser;
use Throwable;

/**
 * RPC客户端类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/6 下午9:34 InteractsWithRpcClient.php $
 * @property App $app
 * @property App $container
 * @method Server getServer()
 * @method Pool getPools()
 */
trait InteractsWithRpcClient
{
    protected function prepareRpcClient()
    {
        $this->onEvent('workerStart', function() {
            $this->bindRpcClientPool();
            $this->bindRpcInterface();
        });
    }
    
    
    protected function bindRpcInterface()
    {
        //引入rpc接口文件
        if (file_exists($rpc = $this->container->getBasePath() . 'rpc.php')) {
            /** @noinspection PhpIncludeInspection */
            $rpcServices = (array) include $rpc;
            
            //绑定rpc接口
            try {
                foreach ($rpcServices as $name => $abstracts) {
                    $parserClass = $this->getSwooleConfig("rpc.client.{$name}.parser", JsonRpcParser::class);
                    $parser      = $this->getApplication()->make($parserClass);
                    $gateway     = new RpcGateway($this->createRpcConnector($name), $parser);
                    $middleware  = $this->getSwooleConfig("rpc.client.{$name}.middleware", []);
                    
                    foreach ($abstracts as $abstract) {
                        $this->getApplication()
                            ->bind($abstract, function(App $app) use ($middleware, $gateway, $name, $abstract) {
                                return $app->invokeClass(Proxy::getClassName($name, $abstract), [
                                    $gateway,
                                    $middleware
                                ]);
                            });
                    }
                }
            } catch (Throwable $e) {
            }
        }
    }
    
    
    protected function bindRpcClientPool()
    {
        if (!empty($clients = $this->getSwooleConfig('rpc.client'))) {
            //创建client连接池
            foreach ($clients as $name => $config) {
                $pool = new ConnectionPool(Pool::pullPoolConfig($config), new Client(), $config);
                $this->getPools()->add("rpc.client.{$name}", $pool);
            }
        }
    }
    
    
    protected function createRpcConnector($name)
    {
        $pool = $this->getPools()->get("rpc.client.{$name}");
        
        return new class($pool) implements Connector {
            use InteractsWithRpcConnector;
            
            protected $pool;
            
            
            public function __construct(ConnectionPool $pool)
            {
                $this->pool = $pool;
            }
            
            
            protected function runWithClient($callback)
            {
                /** @var \Swoole\Coroutine\Client $client */
                $client = $this->pool->borrow();
                try {
                    return $callback($client);
                } finally {
                    $this->pool->return($client);
                }
            }
        };
    }
}
