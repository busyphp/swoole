<?php

namespace BusyPHP\swoole\rpc\client;

use Closure;
use Swoole\Coroutine\Client;
use think\File;
use think\helper\Arr;
use BusyPHP\swoole\concerns\InteractsWithRpcConnector;
use BusyPHP\swoole\contract\rpc\RpcParserInterface;
use BusyPHP\swoole\exception\RpcClientException;
use BusyPHP\swoole\exception\RpcResponseException;
use BusyPHP\swoole\rpc\Error;
use BusyPHP\swoole\rpc\Packer;
use BusyPHP\swoole\rpc\Protocol;
use BusyPHP\swoole\rpc\Sendfile;

/**
 * RPC网关
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/5 下午1:19 RpcGateway.php $
 */
class RpcGateway
{
    use Sendfile;
    
    /** @var Connector */
    protected $connector;
    
    /** @var RpcParserInterface */
    protected $parser;
    
    
    /**
     * Gateway constructor.
     * @param Connector|array    $connector
     * @param RpcParserInterface $parser
     */
    public function __construct($connector, RpcParserInterface $parser)
    {
        if (is_array($connector)) {
            $connector = $this->createDefaultConnector($connector);
        }
        $this->connector = $connector;
        $this->parser    = $parser;
    }
    
    
    protected function encodeData(Protocol $protocol)
    {
        $params = $protocol->getParams();
        
        //有文件,先传输
        foreach ($params as $index => $param) {
            if ($param instanceof File) {
                yield from $this->fread($param);
                $params[$index] = Protocol::FILE;
            }
        }
        
        $protocol->setParams($params);
        
        $data = $this->parser->encode($protocol);
        
        yield Packer::pack($data);
    }
    
    
    protected function decodeResponse($response)
    {
        $result = $this->parser->decodeResponse($response);
        
        if ($result instanceof Error) {
            throw new RpcResponseException($result);
        }
        
        return $result;
    }
    
    
    protected function sendAndRecv($data)
    {
        return $this->connector->sendAndRecv($data, Closure::fromCallable([$this, 'decodeResponse']));
    }
    
    
    public function call(Protocol $protocol)
    {
        $result = backoff(function() use ($protocol) {
            try {
                return $this->sendAndRecv($this->encodeData($protocol));
            } catch (RpcResponseException $e) {
                return $e;
            }
        }, 2);
        
        if ($result instanceof RpcResponseException) {
            throw $result;
        }
        
        return $result;
    }
    
    
    public function getServices()
    {
        return $this->sendAndRecv(Packer::pack(Protocol::ACTION_INTERFACE));
    }
    
    
    protected function createDefaultConnector($config)
    {
        return new class($config) implements Connector {
            use InteractsWithRpcConnector;
            
            /** @var Client */
            protected $client;
            
            protected $config;
            
            
            /**
             *  constructor.
             * @param [] $config
             */
            public function __construct($config)
            {
                $this->config = $config;
            }
            
            
            protected function isConnected() : bool
            {
                return $this->client && $this->client->isConnected() && $this->client->peek() !== '';
            }
            
            
            protected function getClient()
            {
                if (!$this->isConnected()) {
                    $client = new Client(SWOOLE_SOCK_TCP);
                    
                    $host    = Arr::pull($this->config, 'host');
                    $port    = Arr::pull($this->config, 'port');
                    $timeout = Arr::pull($this->config, 'timeout', 5);
                    
                    $client->set($this->config);
                    
                    if (!$client->connect($host, $port, $timeout)) {
                        throw new RpcClientException(sprintf('Connect failed host=%s port=%d', $host, $port));
                    }
                    
                    $this->client = $client;
                }
                
                return $this->client;
            }
            
            
            protected function runWithClient($callback)
            {
                return $callback($this->getClient());
            }
        };
    }
}
