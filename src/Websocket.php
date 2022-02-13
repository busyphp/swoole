<?php

namespace BusyPHP\swoole;

use BusyPHP\Request;
use BusyPHP\swoole\concerns\WithSwooleConfig;
use BusyPHP\swoole\event\WebsocketCloseEvent;
use BusyPHP\swoole\event\WebSocketMessageEvent;
use BusyPHP\swoole\event\WebSocketOpenEvent;
use BusyPHP\swoole\event\WebsocketUserEvent;
use Swoole\Timer;
use Swoole\WebSocket\Frame;
use Swoole\Server;
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
    use WithSwooleConfig;
    
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
     * 发送者FD连接标识符
     * @var int
     */
    protected $sender;
    
    /**
     * 接收者FD连接标识符或房间名称
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
     * 分组前缀
     * @var string
     */
    public $groupPrefix = 'g';
    
    /**
     * UID前缀
     * @var string
     */
    public $uidPrefix = 'u';
    
    
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
        $this->pingInterval = $this->getSwooleConfig('websocket.server.ping_interval', 25000);
        $this->pingTimeout  = $this->getSwooleConfig('websocket.server.ping_timeout', 60000);
    }
    
    
    /**
     * 客户端已连接监听
     * @param int     $fd FD连接标识符
     * @param Request $request 请求
     */
    public function onOpen(int $fd, Request $request) : void
    {
        $event          = new WebSocketOpenEvent();
        $event->fd      = $fd;
        $event->request = $request;
        $this->event->trigger($event);
    }
    
    
    /**
     * 收到消息监听
     * @param Frame $frame 数据帧
     */
    public function onMessage(Frame $frame) : void
    {
        $event        = new WebSocketMessageEvent();
        $event->frame = $frame;
        $this->event->trigger($event);
        
        $data         = $this->decode($frame->data);
        $event        = new WebsocketUserEvent();
        $event->frame = $frame;
        $event->data  = $data['data'] ?? null;
        $event->type  = $data['type'] ?? null;
        $this->event->trigger($event);
    }
    
    
    /**
     * 连接被关闭监听
     * @param int $fd FD连接标识符
     * @param int $reactorId 来自哪个 reactor 线程，主动 close 关闭时为负数
     */
    public function onClose(int $fd, $reactorId) : void
    {
        $event            = new WebsocketCloseEvent();
        $event->fd        = $fd;
        $event->reactorId = $reactorId;
        $this->event->trigger($event);
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
        if (!$values) {
            return $this;
        }
        
        foreach ($values as $value) {
            if (!in_array($value, $this->to)) {
                $this->to[] = $value;
            }
        }
        
        return $this;
    }
    
    
    /**
     * 设置要发送的UID
     * @param int|string|array $uids UID
     * @return $this
     */
    public function toUid($uids) : self
    {
        $uids = is_string($uids) || is_int($uids) ? func_get_args() : $uids;
        if (!$uids) {
            return $this;
        }
        
        foreach ($uids as $value) {
            $value = $this->encodeUid($value);
            if (!in_array($value, $this->to)) {
                $this->to[] = $value;
            }
        }
        
        return $this;
    }
    
    
    /**
     * 设置要发送的分组名称
     * @param int|string|array $groups 分组名称
     * @return $this
     */
    public function toGroup($groups) : self
    {
        $groups = is_string($groups) || is_int($groups) ? func_get_args() : $groups;
        if (!$groups) {
            return $this;
        }
        
        foreach ($groups as $value) {
            $value = $this->encodeGroup($value);
            if (!in_array($value, $this->to)) {
                $this->to[] = $value;
            }
        }
        
        return $this;
    }
    
    
    /**
     * 获取要发送的FD连接标识符或房间名称
     * @return array
     */
    public function getTo() : array
    {
        return $this->to;
    }
    
    
    /**
     * 将当前连接FD标识符加入房间
     * @param string|int|array $rooms 房间名称
     */
    public function join($rooms)
    {
        $this->room->bind($this->getSender(), is_string($rooms) || is_int($rooms) ? func_get_args() : $rooms);
    }
    
    
    /**
     * 将当前连接FD标识符离开房间
     * @param array|string|integer $rooms 房间名称，空则退出所有房间
     * @return $this
     */
    public function leave($rooms = [])
    {
        $this->room->unbind($this->getSender(), is_string($rooms) || is_int($rooms) ? func_get_args() : $rooms);
        
        return $this;
    }
    
    
    /**
     * 将当前连接FD标识符与用户ID绑定
     * @param string|int $uid 用户ID
     */
    public function bindUid($uid)
    {
        if (!$uid) {
            return;
        }
        
        $this->room->bind($this->getSender(), $this->encodeUid($uid));
    }
    
    
    /**
     * 将当前连接FD标识符与用户ID解除绑定
     * @param int|string $uid 用户ID
     */
    public function unbindUid($uid)
    {
        if (!$uid) {
            return;
        }
        
        $this->room->unbind($this->getSender(), $this->encodeUid($uid));
    }
    
    
    /**
     * 通过用户ID获取FD连接标识符列表
     * @param int|string $uid 用户ID
     * @return int[]
     */
    public function getFdListByUid($uid) : array
    {
        if (!$uid) {
            return [];
        }
        
        return $this->room->getFdsByRoom($this->encodeUid($uid));
    }
    
    
    /**
     * 通过FD连接标识符获取用户ID
     * @param int $fd
     * @return string|false
     */
    public function getUidByFd(int $fd)
    {
        $length = strlen($this->uidPrefix);
        foreach ($this->room->getRoomsByFd($fd) as $uid) {
            if (!$this->checkIsUid($uid)) {
                continue;
            }
            
            return substr($uid, $length);
        }
        
        return false;
    }
    
    
    /**
     * 通过分组获取该分组下的所有用户ID列表
     * @param string|int $group 分组名称
     * @return string[]
     */
    public function getUidListByGroup($group) : array
    {
        if (!$group) {
            return [];
        }
        
        $list = [];
        foreach ($this->getFdListByGroup($group) as $fd) {
            if ($uid = $this->getUidByFd($fd)) {
                if (!in_array($uid, $list)) {
                    $list[] = $uid;
                }
            }
        }
        
        return $list;
    }
    
    
    /**
     * 通过用户ID检测是否在线
     * @param int|string $uid 用户ID
     * @return bool
     */
    public function isOnlineByUid($uid) : bool
    {
        $list = $this->getFdListByUid($uid);
        if (!$list) {
            return false;
        }
        
        foreach ($list as $fd) {
            if ($this->isOnline($fd)) {
                return true;
            }
        }
        
        return false;
    }
    
    
    /**
     * 将当前连接FD标识符加入分组
     * @param string|int $group 分组名称
     */
    public function joinGroup($group)
    {
        if (!$group) {
            return;
        }
        
        $this->room->bind($this->getSender(), $this->encodeGroup($group));
    }
    
    
    /**
     * 将当前连接FD标识符离开分组
     * @param int|string $group 分组名称
     */
    public function leaveGroup($group)
    {
        if (!$group) {
            return;
        }
        
        $this->room->unbind($this->getSender(), $this->encodeGroup($group));
    }
    
    
    /**
     * 通过分组获取所有的FD连接标识符列表
     * @param $group
     * @return int[]
     */
    public function getFdListByGroup($group) : array
    {
        if (!$group) {
            return [];
        }
        
        return $this->room->getFdsByRoom($this->encodeGroup($group));
    }
    
    
    /**
     * 通过FD连接标识符获取所有加入的分组列表
     * @param int $fd FD连接标识符
     * @return string[]
     */
    public function getGroupListByFd(int $fd) : array
    {
        if (!$fd) {
            return [];
        }
        
        $length = strlen($this->groupPrefix);
        $list   = [];
        foreach ($this->room->getRoomsByFd($fd) as $group) {
            if (!$this->checkIsGroup($group)) {
                continue;
            }
            $list[] = substr($group, $length);
        }
        
        return $list;
    }
    
    
    /**
     * 通过用户ID获取该用户加入的所有分组列表
     * @param string|int $uid 用户ID
     * @return string[]
     */
    public function getGroupListByUid($uid) : array
    {
        if (!$uid) {
            return [];
        }
        
        $groups = [];
        foreach ($this->getFdListByUid($uid) as $fd) {
            foreach ($this->getGroupListByFd($fd) as $group) {
                if (!in_array($group, $groups)) {
                    $groups[] = $group;
                }
            }
        }
        
        return $groups;
    }
    
    
    /**
     * 编码用户ID
     * @param int|string $name
     * @return string
     */
    final public function encodeUid($name) : string
    {
        return "{$this->uidPrefix}{$name}";
    }
    
    
    /**
     * 解码用户ID
     * @param string $uid 用户ID
     * @param bool   $check 是否检测合规，不合规返回false
     * @return string|false
     */
    final public function decodeUid(string $uid, bool $check = true)
    {
        if ($check && !$this->checkIsUid($uid)) {
            return false;
        }
        
        return substr($uid, strlen($this->uidPrefix));
    }
    
    
    /**
     * 检测是否UID
     * @param $uid
     * @return bool
     */
    final public function checkIsUid($uid) : bool
    {
        return 0 === strpos($uid, $this->uidPrefix);
    }
    
    
    /**
     * 编码分组名
     * @param int|string $name
     * @return string
     */
    final public function encodeGroup($name) : string
    {
        return "{$this->groupPrefix}{$name}";
    }
    
    
    /**
     * 解码分组名
     * @param string $group 分组名
     * @param bool   $check 是否检测合规，不合规返回false
     * @return string|false
     */
    final public function decodeGroup(string $group, bool $check = true)
    {
        if ($check && $this->checkIsGroup($group)) {
            return false;
        }
        
        return substr($group, strlen($this->groupPrefix));
    }
    
    
    /**
     * 检测是否分组
     * @param $group
     * @return bool
     */
    final public function checkIsGroup($group) : bool
    {
        return 0 === strpos($group, $this->groupPrefix);
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
     * @param int|null $fd FD连接标识符
     * @return boolean
     */
    public function close(int $fd = null) : bool
    {
        return (bool) $this->server->close($fd ?: $this->getSender());
    }
    
    
    /**
     * 判断某个FD是否已建立连接
     * @param int|null $fd FD连接标识符
     * @return bool
     */
    public function isOnline(int $fd = null) : bool
    {
        return (bool) $this->server->isEstablished($fd ?: $this->getSender());
    }
    
    
    /**
     * 主动向 WebSocket 客户端发送关闭帧并关闭该连接。
     * @param int|null $fd FD连接标识符
     * @param int      $code 错误码，1000为正常断开
     * @param string   $reason 错误原因
     * @return bool
     */
    public function disconnect(int $fd = null, int $code = 1000, string $reason = '') : bool
    {
        if (!$this->isOnline($fd)) {
            return true;
        }
        
        return (bool) $this->server->disconnect($fd ?: $this->getSender(), $code, $reason);
    }
    
    
    /**
     * 发送数据至客户端
     * @param string   $data 数据
     * @param int|null $fd FD连接标识符
     * @return bool
     */
    public function send(string $data, ?int $fd = null) : bool
    {
        return $this->server->push($fd ?: $this->getSender(), $data);
    }
    
    
    /**
     * 设置发送者FD连接标识符
     * @param int $fd FD连接标识符
     * @return $this
     */
    public function setSender(int $fd)
    {
        $this->sender = $fd;
        $this->reset();
        
        return $this;
    }
    
    
    /**
     * 获取发送者FD连接标识符
     * @return int
     */
    public function getSender() : int
    {
        return (int) ($this->sender ?: 0);
    }
    
    
    /**
     * 获取要将数据推送到的所有FD连接标识符
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
    
    
    /**
     * 获房间驱动
     * @return Room
     */
    public function getRoom() : Room
    {
        return $this->room;
    }
}
