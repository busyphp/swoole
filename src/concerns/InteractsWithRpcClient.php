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
 * Trait InteractsWithRpcClient
 * @package BusyPHP\swoole\concerns
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
                    $parserClass = $this->getConfig("rpc.client.{$name}.parser", JsonRpcParser::class);
                    $parser      = $this->getApplication()->make($parserClass);
                    $gateway     = new RpcGateway($this->createRpcConnector($name), $parser);
                    $middleware  = $this->getConfig("rpc.client.{$name}.middleware", []);
                    
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
        if (!empty($clients = $this->getConfig('rpc.client'))) {
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
