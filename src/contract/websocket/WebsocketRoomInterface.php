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
     * Rooms key
     *
     * @const string
     */
    public const ROOMS_KEY = 'rooms';
    
    /**
     * Descriptors key
     *
     * @const string
     */
    public const DESCRIPTORS_KEY = 'fds';
    
    
    /**
     * Do some init stuffs before workers started.
     *
     * @return WebsocketRoomInterface
     */
    public function prepare() : WebsocketRoomInterface;
    
    
    /**
     * Add multiple socket fds to a room.
     *
     * @param int fd
     * @param array|string rooms
     */
    public function add(int $fd, $rooms);
    
    
    /**
     * Delete multiple socket fds from a room.
     *
     * @param int fd
     * @param array|string rooms
     */
    public function delete(int $fd, $rooms);
    
    
    /**
     * Get all sockets by a room key.
     *
     * @param string room
     *
     * @return array
     */
    public function getClients(string $room);
    
    
    /**
     * Get all rooms by a fd.
     *
     * @param int fd
     *
     * @return array
     */
    public function getRooms(int $fd);
}
