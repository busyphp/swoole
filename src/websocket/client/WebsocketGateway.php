<?php

namespace BusyPHP\swoole\websocket\client;

use BusyPHP\swoole\contract\BaseGateway;
use BusyPHP\swoole\Websocket;
use think\Container;

/**
 * Websocket网关
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/5 下午1:32 WebSocketGateway.php $
 */
class WebsocketGateway extends BaseGateway
{
    /**
     * 断开与指定FD连接标识符的连接
     * @param int    $fd FD连接标识符
     * @param int    $code 断开码，1000位正常断开
     * @param string $reason 断开原因
     * @return bool
     */
    public function disconnect(int $fd, int $code = 1000, string $reason = '') : bool
    {
        if ($this->canRun()) {
            return $this->getWebsocket()->disconnect($fd, $code, $reason);
        }
        
        return $this->gateway(__FUNCTION__, func_get_args());
    }
    
    
    /**
     * 向指定FD连接标识符发送数据
     * @param int    $fd FD连接标识符
     * @param string $data 发送的数据
     * @return bool
     */
    public function send(int $fd, string $data) : bool
    {
        if ($this->canRun()) {
            return $this->getWebsocket()->send($data, $fd);
        }
        
        return $this->gateway(__FUNCTION__, func_get_args());
    }
    
    
    /**
     * 关闭指定FD连接标识符客户端的连接
     * @param int $fd FD连接标识符
     * @return bool
     */
    public function close(int $fd) : bool
    {
        if ($this->canRun()) {
            return $this->getWebsocket()->close($fd);
        }
        
        return $this->gateway(__FUNCTION__, func_get_args());
    }
    
    
    /**
     * 将指定FD连接标识符加入房间
     * @param int              $fd FD连接标识符
     * @param string|int|array $rooms 房间名称
     */
    public function joinRoom(int $fd, $rooms)
    {
        if ($this->canRun()) {
            $this->getWebsocket()->getRoom()->bind($fd, $rooms);
        } else {
            $this->gateway(__FUNCTION__, func_get_args());
        }
    }
    
    
    /**
     * 将指定FD连接标识符离开房间
     * @param int              $fd FD连接标识符
     * @param string|int|array $rooms 房间名称，空则离开所有房间
     */
    public function leaveRoom(int $fd, $rooms)
    {
        if ($this->canRun()) {
            $this->getWebsocket()->getRoom()->unbind($fd, $rooms);
        } else {
            $this->gateway(__FUNCTION__, func_get_args());
        }
    }
    
    
    /**
     * 检测指定FD连接标识符是否在线
     * @param int $fd FD连接标识符
     * @return bool
     */
    public function isOnline(int $fd) : bool
    {
        if ($this->canRun()) {
            return $this->getWebsocket()->isOnline($fd);
        }
        
        return $this->gateway(__FUNCTION__, func_get_args());
    }
    
    
    /**
     * 通过UID检测用户是否在线
     * @param string|int $uid 用户ID
     * @return bool
     */
    public function isOnlineByUid($uid) : bool
    {
        if ($this->canRun()) {
            return $this->getWebsocket()->isOnlineByUid($uid);
        }
        
        return $this->gateway(__FUNCTION__, func_get_args());
    }
    
    
    /**
     * 通过用户ID获取FD连接标识符
     * @param string|int $uid 用户ID
     * @return int 返回0代表不在线
     */
    public function getFdByUid($uid) : int
    {
        if ($this->canRun()) {
            return $this->getWebsocket()->getFdByUid($uid);
        }
        
        return $this->gateway(__FUNCTION__, func_get_args());
    }
    
    
    /**
     * 通过FD连接标识符获取用户ID
     * @param int $fd FD连接标识符
     * @return string|false 不成功返回false，否则返回字符
     */
    public function getUidByFd(int $fd)
    {
        if ($this->canRun()) {
            return $this->getWebsocket()->getUidByFd($fd);
        }
        
        return $this->gateway(__FUNCTION__, func_get_args());
    }
    
    
    /**
     * 通过分组名称获取所有加入的用户ID
     * @param $group
     * @return string[]
     */
    public function getUidListByGroup($group) : array
    {
        if (!$group) {
            return [];
        }
        
        if ($this->canRun()) {
            return $this->getWebsocket()->getUidListByGroup($group);
        }
        
        return $this->gateway(__FUNCTION__, func_get_args());
    }
    
    
    /**
     * 将指定FD连接标识与用户ID绑定
     * @param int        $fd FD连接标识符
     * @param string|int $uid 用户ID
     */
    public function bindUid(int $fd, $uid)
    {
        if (!$uid) {
            return;
        }
        
        if ($this->canRun()) {
            $websocket = $this->getWebsocket();
            $websocket->getRoom()->bind($fd, $websocket->encodeUid($uid));
        } else {
            $this->gateway(__FUNCTION__, func_get_args());
        }
    }
    
    
    /**
     * 将指定FD连接标识与用户ID解除绑定
     * @param int        $fd FD连接标识符
     * @param string|int $uid 用户ID
     */
    public function unbindUid(int $fd, $uid)
    {
        if (!$uid) {
            return;
        }
        
        if ($this->canRun()) {
            $websocket = $this->getWebsocket();
            $websocket->getRoom()->unbind($fd, $websocket->encodeUid($uid));
        } else {
            $this->gateway(__FUNCTION__, func_get_args());
        }
    }
    
    
    /**
     * 通过分组获取该组内的所有FD连接标识符
     * @param int|string $group 分组名称
     * @return int[]
     */
    public function getFdsByGroup($group) : array
    {
        if (!$group) {
            return [];
        }
        
        if ($this->canRun()) {
            return $this->getWebsocket()->getFdsByGroup($group);
        }
        
        return $this->gateway(__FUNCTION__, func_get_args());
    }
    
    
    /**
     * 通过FD连接标识符获取所有加入的分组
     * @param int $fd FD连接标识符
     * @return string[]
     */
    public function getGroupsByFd(int $fd) : array
    {
        if (!$fd) {
            return [];
        }
        
        if ($this->canRun()) {
            return $this->getWebsocket()->getGroupsByFd($fd);
        }
        
        return $this->gateway(__FUNCTION__, func_get_args());
    }
    
    
    /**
     * 通过用户ID获取该用户加入的所有分组
     * @param string|int $uid 用户ID
     * @return string[]
     */
    public function getGroupsByUid($uid) : array
    {
        if (!$uid) {
            return [];
        }
        
        if ($this->canRun()) {
            return $this->getWebsocket()->getGroupsByUid($uid);
        }
        
        return $this->gateway(__FUNCTION__, func_get_args());
    }
    
    
    /**
     * 将指定FD连接标识加入到房间中
     * @param int              $fd FD连接标识符
     * @param array|string|int $groups 房间名称
     */
    public function joinGroup(int $fd, $groups)
    {
        if (!$groups) {
            return;
        }
        
        if ($this->canRun()) {
            if (!is_array($groups)) {
                $groups = [$groups];
            }
            
            $websocket = $this->getWebsocket();
            $list      = [];
            foreach ($groups as $i => $group) {
                $list[] = $websocket->encodeGroup($group);
            }
            
            $websocket->getRoom()->bind($fd, $list);
        } else {
            $this->gateway(__FUNCTION__, func_get_args());
        }
    }
    
    
    /**
     * 将指定FD连接标识离开房间
     * @param int              $fd FD连接标识符
     * @param array|string|int $groups 房间名称
     */
    public function leaveGroup(int $fd, $groups)
    {
        if (!$groups) {
            return;
        }
        
        if ($this->canRun()) {
            if (!is_array($groups)) {
                $groups = [$groups];
            }
            
            $websocket = $this->getWebsocket();
            $list      = [];
            foreach ($groups as $i => $group) {
                $list[] = $websocket->encodeGroup($group);
            }
            
            $websocket->getRoom()->unbind($fd, $list);
        } else {
            $this->gateway(__FUNCTION__, func_get_args());
        }
    }
    
    
    /**
     * 发送数据给所有人
     * @param string $data 发送的数据
     */
    public function sendToAll(string $data)
    {
        if ($this->canRun()) {
            $this->getWebsocket()->broadcast()->push($data);
        } else {
            $this->gateway(__FUNCTION__, func_get_args());
        }
    }
    
    
    /**
     * 发送数据给指定的用户ID
     * @param string|int $uid 用户ID
     * @param string     $data 发送的数据
     */
    public function sendToUid($uid, string $data)
    {
        $this->batchSendToUid([$uid => $data]);
    }
    
    
    /**
     * 批量发送不同的数据给不同的用户
     * @param array $pairs 键值对数据：[用户ID => 数据内容, 用户ID => 数据内容]
     */
    public function batchSendToUid(array $pairs)
    {
        if (!$pairs) {
            return;
        }
        
        if ($this->canRun()) {
            $websocket = $this->getWebsocket();
            foreach ($pairs as $uid => $data) {
                if (!$data) {
                    continue;
                }
                
                $websocket->toUid($uid)->push($data);
            }
        } else {
            $this->gateway(__FUNCTION__, func_get_args());
        }
    }
    
    
    /**
     * 批量发送相同的数据给不同的用户ID
     * @param array  $uids 用户ID集合
     * @param string $data 数据
     */
    public function batchSendDataToUids(array $uids, string $data)
    {
        if (!$uids) {
            return;
        }
        
        if ($this->canRun()) {
            $this->getWebsocket()->toUid($uids)->push($data);
        } else {
            $this->gateway(__FUNCTION__, func_get_args());
        }
    }
    
    
    /**
     * 发送数据给分组
     * @param string|int $room 分组名称
     * @param string     $data 数据内容
     */
    public function sendToGroup($room, string $data)
    {
        $this->batchSendToGroup([$room => $data]);
    }
    
    
    /**
     * 批量发送不同的数据给不同的分组
     * @param array $pairs 键值对数据：[分组名称 => 数据内容, 分组数据1 => 数据内容]
     */
    public function batchSendToGroup(array $pairs)
    {
        if (!$pairs) {
            return;
        }
        
        if ($this->canRun()) {
            $websocket = $this->getWebsocket();
            foreach ($pairs as $group => $data) {
                if (!$data) {
                    continue;
                }
                
                $websocket->toGroup($group)->push($data);
            }
        } else {
            $this->gateway(__FUNCTION__, func_get_args());
        }
    }
    
    
    /**
     * 批量发送相同的数据给不同的分组
     * @param array  $groups 分组名称
     * @param string $data 数据
     */
    public function batchSendDataToGroups(array $groups, string $data)
    {
        if (!$groups) {
            return;
        }
        
        if ($this->canRun()) {
            $this->getWebsocket()->toGroup($groups)->push($data);
        } else {
            $this->gateway(__FUNCTION__, func_get_args());
        }
    }
    
    
    /**
     * 发送数据给指定的房间
     * @param string|int $room 房间名称
     * @param string     $data 发送的数据
     */
    public function sendToRoom($room, string $data)
    {
        $this->batchSendToRoom([$room => $data]);
    }
    
    
    /**
     * 批量发送不同的数据给不同的房间
     * @param array $pairs 键值对数据：[房间名称 => 数据内容, 房间名称 => 数据内容]
     */
    public function batchSendToRoom(array $pairs)
    {
        if (!$pairs) {
            return;
        }
        
        if ($this->canRun()) {
            $websocket = $this->getWebsocket();
            foreach ($pairs as $room => $data) {
                if (!$data) {
                    continue;
                }
                
                $websocket->to($room)->push($data);
            }
        } else {
            $this->gateway(__FUNCTION__, func_get_args());
        }
    }
    
    
    /**
     * 批量发送相同的数据给不同的房间
     * @param array  $rooms 房间集合
     * @param string $data 数据
     */
    public function batchSendDataToRooms(array $rooms, string $data)
    {
        if (!$rooms) {
            return;
        }
        
        if ($this->canRun()) {
            $this->getWebsocket()->to($rooms)->push($data);
        } else {
            $this->gateway(__FUNCTION__, func_get_args());
        }
    }
    
    
    /**
     * 获取Websocket对象
     * @return Websocket
     */
    protected function getWebsocket() : Websocket
    {
        return Container::getInstance()->make(Websocket::class);
    }
}