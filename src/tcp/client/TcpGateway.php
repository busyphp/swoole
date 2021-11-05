<?php

namespace BusyPHP\swoole\tcp\client;

use BusyPHP\App;
use BusyPHP\exception\VerifyException;
use RuntimeException;
use swoole_client;
use think\Config;
use think\Container;

/**
 * TCP网关
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/5 上午11:22 TcpGateway.php $
 */
class TcpGateway
{
    /**
     * 网关参数标识
     * @var string
     */
    public static $prefix = '##gateway##';
    
    /**
     * 签名密钥
     * @var string
     */
    protected static $secret = 'sNUn6opBU9P8RdXe9mnH73v8';
    
    /**
     * @var App
     */
    protected $app;
    
    /**
     * 网关入口
     * @var string
     */
    protected $host;
    
    /**
     * 端口号
     * @var string
     */
    protected $port;
    
    /**
     * @var Config
     */
    protected $config;
    
    
    /**
     * TcpGateway constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app    = $app;
        $this->config = $this->app->config;
        $this->host   = $this->config->get('swoole.server.host', '') ?: '127.0.0.1';
        $this->port   = $this->config->get('swoole.tcp.server.port', '') ?: 8081;
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
     * @param int    $clientId 客户端ID
     * @param string $data 数据
     */
    public function send(int $clientId, string $data)
    {
        if (!$data) {
            throw new VerifyException('发送数据不能为空', 'empty');
        }
        
        $content = base64_encode(json_encode(['client_id' => $clientId, 'data' => $data]));
        $time    = time();
        $sign    = self::sign($content, $time);
        $content = self::$prefix . "{$time},{$sign},{$content}";
        
        // 运行在Swoole下
        if ($this->app instanceof \BusyPHP\swoole\App) {
            $client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC);
            if (!$client->connect($this->host, $this->port)) {
                throw new RuntimeException("连接失败, host: {$this->host}, port: {$this->port}");
            }
            $client->send($content);
            $res = $client->recv();
            $client->close();
        } else {
            if (!function_exists('socket_create')) {
                throw new RuntimeException('没有安装 sockets 扩展');
            }
            
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if (!socket_connect($socket, $this->host, $this->port)) {
                throw new RuntimeException("连接失败, host: {$this->host}, port: {$this->port}");
            }
            
            if (!socket_write($socket, $content, strlen($content))) {
                throw new RuntimeException('写入数据失败');
            }
            
            $res = socket_read($socket, 8190);
            socket_close($socket);
        }
        
        if ($res !== 'success') {
            throw new RuntimeException($res);
        }
    }
    
    
    /**
     * 生成签名
     * @param string $str
     * @param int    $time
     * @return string
     */
    public static function sign(string $str, int $time) : string
    {
        $temp = [$str, $time, self::$secret];
        
        return md5(implode(',', $temp));
    }
    
    
    /**
     * 验证签名
     * @param string $sign
     * @param string $str
     * @param int    $time
     * @return bool
     */
    public static function verify(string $sign, string $str, int $time) : bool
    {
        return $sign === self::sign($str, $time);
    }
}