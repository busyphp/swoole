<?php

namespace BusyPHP\swoole\websocket\client;

use BusyPHP\swoole\App;
use RuntimeException;
use Swlib\Saber\WebSocket;
use Swoole\Process;
use Swoole\Server;
use Swoole\WebSocket\CloseFrame;
use Swoole\WebSocket\Frame;
use Throwable;

/**
 * WebSocket客户端基本类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2022 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2022/2/13 10:51 AM WebSocketClient.php $
 */
abstract class WebSocketClient
{
    /**
     * @var WebSocket
     */
    private $websocket;
    
    /**
     * @var Process
     */
    protected $process;
    
    /**
     * @var string
     */
    protected $url;
    
    /**
     * @var Server
     */
    protected $server;
    
    /**
     * @var App
     */
    protected $app;
    
    
    /**
     * @param App     $app
     * @param Server  $server
     * @param Process $process
     * @param string  $url
     */
    public function __construct(App $app, Server $server, Process $process, string $url)
    {
        $this->process = $process;
        $this->url     = $url;
        $this->server  = $server;
        $this->app     = $app;
    }
    
    
    /**
     * 设置Websocket客户端连接器
     * @param WebSocket $websocket
     */
    public function setWebsocket(WebSocket $websocket) : void
    {
        $this->websocket = $websocket;
    }
    
    
    /**
     * 检测是否已连接
     */
    private function check()
    {
        if (!$this->websocket) {
            throw new RuntimeException('Websocket not connected');
        }
    }
    
    
    /**
     * 发送数据
     * @param string $data
     * @param int    $opcode
     * @param bool   $finish
     * @return bool
     */
    public function send(string $data, int $opcode = WEBSOCKET_OPCODE_TEXT, bool $finish = true) : bool
    {
        $this->check();
        
        return $this->websocket->push($data, $opcode, $finish);
    }
    
    
    /**
     * 关闭连接
     * @return bool
     */
    public function close() : bool
    {
        $this->check();
        
        return $this->websocket->close();
    }
    
    
    /**
     * 断开链接并强制重连
     * 本操作会强制重启进程达到重连的效果
     */
    public function reconnect()
    {
        try {
            $this->close();
        } catch (Throwable $e) {
        }
        
        $this->process->exit();
    }
    
    
    /**
     * 准备连接前触发
     */
    abstract public function onBefore();
    
    
    /**
     * 已建立连接时触发
     */
    abstract public function onOpen();
    
    
    /**
     * 收到消息时触发
     * @param Frame $frame
     */
    abstract public function onMessage(Frame $frame);
    
    
    /**
     * 连接被关闭时触发
     * @param CloseFrame $closeFrame
     */
    abstract public function onClose(CloseFrame $closeFrame);
    
    
    /**
     * 连接错误时触发
     * @param Throwable $e
     */
    abstract public function onError(Throwable $e);
}