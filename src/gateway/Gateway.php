<?php

namespace BusyPHP\swoole\gateway;

use BusyPHP\App;
use BusyPHP\swoole\concerns\WithSwooleConfig;
use RuntimeException;
use Swoole\Client;
use think\Container;

/**
 * 网关
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/6 下午2:09 Gateway.php $
 */
class Gateway
{
    use WithSwooleConfig;
    
    /**
     * @var App
     */
    protected $app;
    
    /**
     * @var string
     */
    private $host;
    
    /**
     * @var int
     */
    private $port;
    
    /**
     * @var string
     */
    private $secret;
    
    
    /**
     * TcpGateway constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app    = $app;
        $this->host   = $this->getSwooleConfig('server.host', '') ?: '127.0.0.1';
        $this->port   = $this->getSwooleConfig('gateway.server.port', '') ?: 8083;
        $this->secret = $this->getSwooleConfig('gateway.secret', '') ?: 'sNUn6opBU9P8RdXe9mnH73v8';
    }
    
    
    /**
     * 单例模式
     * @return static
     */
    public static function init() : self
    {
        return Container::getInstance()->make(static::class);
    }
    
    
    /**
     * 给客户端发送送数据
     * @param string $class 发送类
     * @param string $method 类方法
     * @param array  $args 方法参数
     * @return mixed
     */
    public function send(string $class, string $method, array $args = [])
    {
        $content = (string) @json_encode([
            'secret' => $this->secret,
            'class'  => $class,
            'method' => $method,
            'args'   => $args,
        ]);
        $client  = new Client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC);
        if (!$client->connect($this->host, $this->port)) {
            throw new RuntimeException("连接失败, host: {$this->host}, port: {$this->port}");
        }
        $client->send($content);
        $result = $client->recv();
        $client->close();
        
        $result           = json_decode($result, true) ?: [];
        $result['status'] = $result['status'] ?? false;
        if (!$result['status']) {
            throw new RuntimeException($result['message'] ?? '未知错误');
        }
        
        return $result['result'];
    }
    
    
    /**
     * 获取通信密钥
     * @return string
     */
    public function getSecret() : string
    {
        return $this->secret;
    }
}