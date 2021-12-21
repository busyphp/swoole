<?php

namespace BusyPHP\swoole;

use BusyPHP\Request;
use Swoole\Server;
use Swoole\Timer;
use Swoole\WebSocket\Frame;
use think\Event;
use BusyPHP\swoole\websocket\Pusher;
use BusyPHP\swoole\websocket\Room;

/**
 * WebSocket类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/3 下午9:15 Websocket.php $
 */
class Websocket
{
    /**
     * @var \BusyPHP\App
     */
    protected $app;
    
    /**
     * @var Server
     */
    protected $server;
    
    /**
     * @var Room
     */
    protected $room;
    
    /**
     * 发送者客户端ID(fd)
     * @var int
     */
    protected $sender;
    
    /**
     * 接收者fd或房间名称
     * @var array
     */
    protected $to = [];
    
    /**
     * 是否要发送广播
     * @var boolean
     */
    protected $isBroadcast = false;
    
    /**
     * @var Event
     */
    protected $event;
    
    /**
     * ping包超时计时器
     * @var int
     */
    protected $pingTimeoutTimer = null;
    
    /**
     * ping包发送计时器
     * @var int
     */
    protected $pingIntervalTimer = null;
    
    /**
     * ping包间隔毫秒
     * @var int
     */
    protected $pingInterval;
    
    /**
     * ping包超时毫秒
     * @var int
     */
    protected $pingTimeout;
    
    
    /**
     * Websocket constructor.
     *
     * @param \BusyPHP\App $app
     * @param Server       $server
     * @param Room         $room
     * @param Event        $event
     */
    public function __construct(\BusyPHP\App $app, Server $server, Room $room, Event $event)
    {
        $this->app          = $app;
        $this->server       = $server;
        $this->room         = $room;
        $this->event        = $event;
        $this->pingInterval = $this->app->config->get('swoole.websocket.ping_interval', 25000);
        $this->pingTimeout  = $this->app->config->get('swoole.websocket.ping_timeout', 60000);
    }
    
    
    /**
     * 客户端已连接监听
     * @param int     $fd
     * @param Request $request
     */
    public function onOpen(int $fd, Request $request) : void
    {
        $this->event->trigger('swoole.websocket.open', [$fd, $request]);
    }
    
    
    /**
     * 收到消息监听
     * @param Frame $frame
     */
    public function onMessage(Frame $frame) : void
    {
        $this->event->trigger('swoole.websocket.message', $frame);
        $this->event->trigger('swoole.websocket.event', [$frame, $this->decode($frame->data)]);
    }
    
    
    /**
     * 连接被关闭监听
     * @param int $fd 客户端ID
     * @param int $reactorId 来自哪个 reactor 线程，主动 close 关闭时为负数
     */
    public function onClose(int $fd, $reactorId) : void
    {
        $this->event->trigger('swoole.websocket.close', [$fd, $reactorId]);
    }
    
    
    /**
     * 设置当前要发送广播
     */
    public function broadcast() : self
    {
        $this->isBroadcast = true;
        
        return $this;
    }
    
    
    /**
     * 判断是否发送广播
     */
    public function isBroadcast() : bool
    {
        return $this->isBroadcast;
    }
    
    
    /**
     * 设置要发送的客户端或房间名称
     * @param int|string|array
     * @return $this
     */
    public function to($values) : self
    {
        $values = is_string($values) || is_int($values) ? func_get_args() : $values;
        
        foreach ($values as $value) {
            if (!in_array($value, $this->to)) {
                $this->to[] = $value;
            }
        }
        
        return $this;
    }
    
    
    /**
     * 设置要发送的客户端名称
     * @param int|string|array $clients
     * @return $this
     */
    public function toClient($clients) : self
    {
        $clients = is_string($clients) || is_int($clients) ? func_get_args() : $clients;
        foreach ($clients as $value) {
            $value = $this->clientName($value);
            if (!in_array($value, $this->to)) {
                $this->to[] = $value;
            }
        }
        
        return $this;
    }
    
    
    /**
     * 设置要发送的房间名称
     * @param int|string|array $rooms
     * @return $this
     */
    public function toRoom($rooms) : self
    {
        $rooms = is_string($rooms) || is_int($rooms) ? func_get_args() : $rooms;
        foreach ($rooms as $value) {
            $value = $this->roomName($value);
            if (!in_array($value, $this->to)) {
                $this->to[] = $value;
            }
        }
        
        return $this;
    }
    
    
    /**
     * 获取要发送的客户端ID(fd)或房间名称
     * @return array
     */
    public function getTo() : array
    {
        return $this->to;
    }
    
    
    /**
     * 将发送者加入到分组
     * @param string|int|array $groups 分组名称
     */
    public function join($groups)
    {
        $groups = is_string($groups) || is_int($groups) ? func_get_args() : $groups;
        
        $this->room->bind($this->getSender(), $groups);
    }
    
    
    /**
     * 将发送者加入到客户端
     * @param string|int|array $clients 客户端名称
     */
    public function joinClient($clients)
    {
        $clients = is_string($clients) || is_int($clients) ? func_get_args() : $clients;
        foreach ($clients as $i => $room) {
            $clients[$i] = $this->clientName($room);
        }
        
        $this->room->bind($this->getSender(), $clients);
    }
    
    
    /**
     * 加发送者加入到房间
     * @param string|int|array $rooms 房间名称
     */
    public function joinRoom($rooms)
    {
        $rooms = is_string($rooms) || is_int($rooms) ? func_get_args() : $rooms;
        foreach ($rooms as $i => $room) {
            $rooms[$i] = $this->roomName($room);
        }
        
        $this->room->bind($this->getSender(), $rooms);
    }
    
    
    /**
     * 设置发送者离开分组
     * @param array|string|integer $groups 分组名称
     * @return $this
     */
    public function leave($groups = []) : self
    {
        $groups = is_string($groups) || is_int($groups) ? func_get_args() : $groups;
        
        $this->room->unbind($this->getSender(), $groups);
        
        return $this;
    }
    
    
    /**
     * 设置发送者离开客户端
     * @param array|int|string $clients 客户端名称
     * @return $this
     */
    public function leaveClient($clients = []) : self
    {
        $clients = is_string($clients) || is_int($clients) ? func_get_args() : $clients;
        foreach ($clients as $i => $group) {
            $clients[$i] = $this->clientName($group);
        }
        
        $this->room->unbind($this->getSender(), $clients);
        
        return $this;
    }
    
    
    /**
     * 设置发送者离开房间
     * @param array|int|string $rooms 房间名称
     * @return $this
     */
    public function leaveRoom($rooms = []) : self
    {
        $rooms = is_string($rooms) || is_int($rooms) ? func_get_args() : $rooms;
        foreach ($rooms as $i => $group) {
            $rooms[$i] = $this->roomName($group);
        }
        
        $this->room->unbind($this->getSender(), $rooms);
        
        return $this;
    }
    
    
    /**
     * 推送数据
     * @param mixed $data
     * @return bool
     */
    public function push($data) : bool
    {
        $fds      = $this->getFds();
        $assigned = !empty($this->getTo());
        
        try {
            if (empty($fds) && $assigned) {
                return false;
            }
            
            $job = new Job([Pusher::class, 'push'], [
                'sender'      => $this->getSender() ?: 0,
                'descriptors' => $fds,
                'broadcast'   => $this->isBroadcast(),
                'assigned'    => $assigned,
                'payload'     => $data,
            ]);
            
            if ($this->server->taskworker) {
                $result = $job->run($this->app);
            } else {
                $result = $this->server->task($job);
            }
            
            return $result !== false;
        } finally {
            $this->reset();
        }
    }
    
    
    /**
     * 推送带有事件名的数据
     * @param string $event
     * @param mixed  ...$data
     * @return bool
     */
    public function emit(string $event, ...$data) : bool
    {
        return $this->push($this->encode([
            'type' => $event,
            'data' => $data,
        ]));
    }
    
    
    /**
     * 生成数据
     * @param $packet
     * @return string
     */
    protected function encode($packet)
    {
        return (string) json_encode($packet);
    }
    
    
    /**
     * 解析数据
     * @param string $payload
     * @return array
     */
    protected function decode($payload)
    {
        $data = json_decode($payload, true) ?: [];
        
        return [
            'type' => $data['type'] ?? null,
            'data' => $data['data'] ?? null,
        ];
    }
    
    
    /**
     * 关闭客户端
     * @param int|null $fd
     * @return boolean
     */
    public function close(int $fd = null)
    {
        return $this->server->close($fd ?: $this->getSender());
    }
    
    
    /**
     * 判断某个客户端(fd)是否已建立连接
     * @param int|null $fd
     * @return bool
     */
    public function isEstablished(int $fd = null) : bool
    {
        return (bool) $this->server->isEstablished($fd ?: $this->getSender());
    }
    
    
    /**
     * 与客户端断开连接
     * @param int|null $fd 客户端ID
     * @param int      $code 错误码
     * @param string   $reason 错误原因
     * @return bool
     */
    public function disconnect(int $fd = null, int $code = 1000, string $reason = '') : bool
    {
        return (bool) $this->server->disconnect($fd ?: $this->getSender(), $code, $reason);
    }
    
    
    /**
     * 设置发送者客户端ID(fd)
     * @param int
     * @return $this
     */
    public function setSender(int $fd)
    {
        $this->sender = $fd;
        $this->reset();
        
        return $this;
    }
    
    
    /**
     * 获取发送者客户端ID
     * @return int
     */
    public function getSender() : int
    {
        return (int) ($this->sender ?: 0);
    }
    
    
    /**
     * 获取我们要将数据推送到的所有fd
     * @return array
     */
    public function getFds() : array
    {
        $to    = $this->getTo();
        $fds   = array_filter($to, function($value) {
            return is_int($value);
        });
        $rooms = array_diff($to, $fds);
        
        foreach ($rooms as $room) {
            $clients = $this->room->getFdsByRoom($room);
            // fallback fd with wrong type back to fds array
            if (empty($clients) && is_numeric($room)) {
                $fds[] = $room;
            } else {
                $fds = array_merge($fds, $clients);
            }
        }
        
        return array_values(array_unique($fds));
    }
    
    
    /**
     * 重置参数
     */
    protected function reset()
    {
        $this->isBroadcast = false;
        $this->to          = [];
    }
    
    
    /**
     * 生成客户端名称
     * @param int|string $name
     * @return string
     */
    public function clientName($name) : string
    {
        return "u{$name}";
    }
    
    
    /**
     * 生成房间名称
     * @param int|string $name
     * @return string
     */
    public function roomName($name) : string
    {
        return "g{$name}";
    }
    
    
    /**
     * 重置ping包超时计时
     * @param $timeout
     */
    protected function resetPingTimeout($timeout)
    {
        Timer::clear($this->pingTimeoutTimer);
        $this->pingTimeoutTimer = Timer::after($timeout, function() {
            $this->close();
        });
    }
    
    
    /**
     * 启动发送ping包
     */
    protected function schedulePing()
    {
        Timer::clear($this->pingIntervalTimer);
        $this->pingIntervalTimer = Timer::after($this->pingInterval, function() {
            $this->push($this->pingData());
            $this->resetPingTimeout($this->pingTimeout);
        });
    }
    
    
    /**
     * ping包内容
     * @return string
     */
    protected function pingData() : string
    {
        return "ping";
    }
}
