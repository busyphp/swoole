<?php

namespace BusyPHP\swoole\contract\websocket;

/**
 * Websocket房间接口类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/5 上午10:44 WebsocketRoomInterface.php $
 */
interface WebsocketRoomInterface
{
    /**
     * 房间键
     * - 房间A => [fd1, fd2, fd3, ....]
     * - 房间B => [fd1, fd2, fd3, ....]
     * @var string
     */
    public const ROOMS_KEY = 'rooms';
    
    /**
     * FD关系键
     * - FD1 => [房间A, 房间B, 房间C, ....]
     * - FD2 => [房间A, 房间B, 房间C, ....]
     * @var string
     */
    public const DESCRIPTORS_KEY = 'fds';
    
    
    /**
     * 在驱动开始工作前做一些初始工作
     * @return WebsocketRoomInterface
     */
    public function prepare() : WebsocketRoomInterface;
    
    
    /**
     * 将FD和房间进行关系绑定
     * @param int          $fd FD
     * @param array|string $rooms 房间名称
     */
    public function bind(int $fd, $rooms);
    
    
    /**
     * 删除FD和房间的绑定关系
     * @param int          $fd FD
     * @param array|string $rooms 房间名称
     */
    public function unbind(int $fd, $rooms = []);
    
    
    /**
     * 通过房间名获取所有加入该房间的FD
     * @param string|int $room 房间名
     * @return array
     */
    public function getFdsByRoom($room) : array;
    
    
    /**
     * 通过FD获取该FD加入的所有房间
     * @param int $fd FD
     * @return array
     */
    public function getRoomsByFd(int $fd) : array;
}
