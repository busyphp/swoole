<?php

namespace BusyPHP\swoole\websocket\socketio;

use BusyPHP\Request;
use BusyPHP\swoole\event\WebsocketCloseEvent;
use BusyPHP\swoole\event\WebsocketConnectEvent;
use BusyPHP\swoole\event\WebsocketDisconnectEvent;
use BusyPHP\swoole\event\WebSocketMessageEvent;
use BusyPHP\swoole\event\WebSocketOpenEvent;
use BusyPHP\swoole\event\WebsocketUserEvent;
use Exception;
use Swoole\Timer;
use Swoole\Websocket\Frame;
use BusyPHP\swoole\Websocket;

/**
 * Websocket默认事件处理器
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/6 下午7:03 WebsocketHandler.php $
 */
class WebsocketHandler extends Websocket
{
    protected $eio;
    
    
    /**
     * 已连接事件
     * @param int     $fd
     * @param Request $request
     */
    public function onOpen(int $fd, Request $request) : void
    {
        $this->eio = $request->param('EIO');
        
        $payload = json_encode([
            'sid'          => base64_encode(uniqid()),
            'upgrades'     => [],
            'pingInterval' => $this->pingInterval,
            'pingTimeout'  => $this->pingTimeout,
        ]);
        
        $this->push(EnginePacket::open($payload));
        
        $event          = new WebSocketOpenEvent();
        $event->fd      = $fd;
        $event->request = $request;
        $this->event->trigger($event);
        
        if ($this->eio < 4) {
            $this->resetPingTimeout($this->pingInterval + $this->pingTimeout);
            $this->onConnect($fd);
        } else {
            $this->schedulePing();
        }
    }
    
    
    /**
     * 收到消息事件
     * @param Frame $frame
     */
    public function onMessage(Frame $frame) : void
    {
        $enginePacket = EnginePacket::fromString($frame->data);
        
        $event         = new WebSocketMessageEvent();
        $event->frame  = $event;
        $event->packet = $enginePacket;
        $this->event->trigger($event);
        
        $this->resetPingTimeout($this->pingInterval + $this->pingTimeout);
        
        switch ($enginePacket->type) {
            case EnginePacket::MESSAGE:
                $packet = $this->decode($enginePacket->data);
                switch ($packet->type) {
                    case Packet::CONNECT:
                        $this->onConnect($frame->fd, $packet->data);
                    break;
                    case Packet::EVENT:
                        $type = array_shift($packet->data);
                        $data = $packet->data;
                        
                        $event        = new WebsocketUserEvent();
                        $event->frame = $frame;
                        $event->type  = $type;
                        $event->data  = $data;
                        $result       = $this->event->trigger($event);
                        
                        if ($packet->id !== null) {
                            $responsePacket = Packet::create(Packet::ACK, [
                                'id'   => $packet->id,
                                'nsp'  => $packet->nsp,
                                'data' => $result,
                            ]);
                            
                            $this->push($responsePacket);
                        }
                    break;
                    case Packet::DISCONNECT:
                        
                        $event        = new WebsocketDisconnectEvent();
                        $event->frame = $frame;
                        $this->event->trigger($event);
                        $this->close();
                    break;
                    default:
                        $this->close();
                    break;
                }
            break;
            case EnginePacket::PING:
                $this->push(EnginePacket::pong($enginePacket->data));
            break;
            case EnginePacket::PONG:
                $this->schedulePing();
            break;
            default:
                $this->close();
            break;
        }
    }
    
    
    /**
     * 连接已关闭事件
     * @param int $fd
     * @param int $reactorId
     */
    public function onClose(int $fd, $reactorId) : void
    {
        Timer::clear($this->pingTimeoutTimer);
        Timer::clear($this->pingIntervalTimer);
        
        $event            = new WebsocketCloseEvent();
        $event->fd        = $fd;
        $event->reactorId = $reactorId;
        $this->event->trigger($event);
    }
    
    
    protected function onConnect(int $fd, $data = null)
    {
        try {
            $event       = new WebsocketConnectEvent();
            $event->fd   = $fd;
            $event->data = $data;
            $this->event->trigger($event);
            
            $packet = Packet::create(Packet::CONNECT);
            if ($this->eio >= 4) {
                $packet->data = ['sid' => base64_encode(uniqid())];
            }
        } catch (Exception $exception) {
            $packet = Packet::create(Packet::CONNECT_ERROR, [
                'data' => ['message' => $exception->getMessage()],
            ]);
        }
        
        $this->push($packet);
    }
    
    
    /**
     * 生成数据
     * @param $packet
     * @return string
     */
    protected function encode($packet)
    {
        return Parser::encode($packet);
    }
    
    
    /**
     * 解析数据
     * @param string $payload
     * @return Packet
     */
    protected function decode($payload)
    {
        return Parser::decode($payload);
    }
    
    
    /**
     * 推送数据
     * @param mixed $data
     * @return bool
     */
    public function push($data) : bool
    {
        if ($data instanceof Packet) {
            $data = EnginePacket::message($this->encode($data));
        }
        if ($data instanceof EnginePacket) {
            $data = $data->toString();
        }
        
        return parent::push($data);
    }
    
    
    /**
     * 推送带有事件名的数据
     * @param string $event
     * @param mixed  ...$data
     * @return bool
     */
    public function emit(string $event, ...$data) : bool
    {
        $packet = Packet::create(Packet::EVENT, [
            'data' => array_merge([$event], $data),
        ]);
        
        return $this->push($packet);
    }
    
    
    /**
     * ping包内容
     * @return string
     */
    protected function pingData() : string
    {
        return EnginePacket::ping();
    }
}
