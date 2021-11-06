<?php

namespace BusyPHP\swoole\websocket\client;

use BusyPHP\exception\VerifyException;
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
     * 发送数据给指定的客户端
     * @param string|int $client 客户端名称
     * @param string     $data 发送的数据
     */
    public function sendToClient($client, string $data)
    {
        $this->sendToClients([$client => $data]);
    }
    
    
    /**
     * 发送数据给指定的一批客户端
     * @param array $clients 客户端键值对，如：[客户端名称 => 数据内容, 客户端名称 => 数据内容]
     */
    public function sendToClients(array $clients)
    {
        if (!$clients) {
            throw new VerifyException('未指定客户端名称');
        }
        
        if ($this->canRun()) {
            $websocket = $this->getWebsocket();
            foreach ($clients as $client => $data) {
                if (!$data) {
                    continue;
                }
                
                $websocket->toClient($client)->push($data);
            }
        } else {
            $this->gateway(__FUNCTION__, func_get_args());
        }
    }
    
    
    /**
     * 发送相同的数据给一批客户端
     * @param array  $clients 客户端名称
     * @param string $data 数据
     */
    public function sendToClientsEqualData($clients, string $data)
    {
        if (!$clients) {
            throw new VerifyException('未指定客户端名称');
        }
        
        if ($this->canRun()) {
            $this->getWebsocket()->toClient($clients)->push($data);
        } else {
            $this->gateway(__FUNCTION__, func_get_args());
        }
    }
    
    
    /**
     * 发送数据给指定的房间
     * @param string|int $room 房间名称
     * @param string     $data 数据内容
     */
    public function sendToRoom($room, string $data)
    {
        $this->sendToRooms([$room => $data]);
    }
    
    
    /**
     * 发送数据给指定的一批房间
     * @param array $rooms 房间键值对，如：[房间名称 => 数据内容, 房间名称 => 数据内容]
     */
    public function sendToRooms(array $rooms)
    {
        if (!$rooms) {
            throw new VerifyException('未指定房间');
        }
        
        if ($this->canRun()) {
            $websocket = $this->getWebsocket();
            foreach ($rooms as $room => $data) {
                if (!$data) {
                    continue;
                }
                
                $websocket->toRoom($room)->push($data);
            }
        } else {
            $this->gateway(__FUNCTION__, func_get_args());
        }
    }
    
    
    /**
     * 发送相同的数据给一批房间
     * @param array  $rooms 房间名称
     * @param string $data 数据
     */
    public function sendToRoomsEqualData($rooms, string $data)
    {
        if (!$rooms) {
            throw new VerifyException('未指定房间');
        }
        
        if ($this->canRun()) {
            $this->getWebsocket()->toRoom($rooms)->push($data);
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