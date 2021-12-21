<?php
declare(strict_types = 1);

namespace BusyPHP\swoole\websocket\room;

use InvalidArgumentException;
use Swoole\Table as SwooleTable;
use BusyPHP\swoole\contract\websocket\WebsocketRoomInterface;

/**
 * Swoole Table 房间驱动
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/21 下午2:38 Table.php $
 */
class Table implements WebsocketRoomInterface
{
    /**
     * @var array
     */
    protected $config = [
        'room_rows'   => 4096,
        'room_size'   => 2048,
        'client_rows' => 8192,
        'client_size' => 2048,
    ];
    
    /**
     * @var SwooleTable
     */
    protected $rooms;
    
    /**
     * @var SwooleTable
     */
    protected $fds;
    
    
    /**
     * TableRoom constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = array_merge($this->config, $config);
    }
    
    
    /**
     * 初始化SwooleTable
     * @return WebsocketRoomInterface
     */
    public function prepare() : WebsocketRoomInterface
    {
        // 初始化 房间 => [fd] 关系表
        $this->rooms = new SwooleTable($this->config['room_rows']);
        $this->rooms->column('value', SwooleTable::TYPE_STRING, $this->config['room_size']);
        $this->rooms->create();
        
        // 初始化 fd => [房间] 关系表
        $this->fds = new SwooleTable($this->config['client_rows']);
        $this->fds->column('value', SwooleTable::TYPE_STRING, $this->config['client_size']);
        $this->fds->create();
        
        return $this;
    }
    
    
    /**
     * 将FD和房间进行关系绑定
     * @param int          $fd FD
     * @param array|string $rooms 房间名称
     */
    public function bind(int $fd, $rooms)
    {
        $rooms = $this->getRoomsByFd($fd);
        $rooms = is_array($rooms) ? $rooms : [$rooms];
        
        foreach ($rooms as $room) {
            $fds = $this->getFdsByRoom($room);
            
            if (in_array($fd, $fds)) {
                continue;
            }
            
            $fds[]   = $fd;
            $rooms[] = $room;
            
            $this->setRoomFds($room, $fds);
        }
        
        $this->setFdRooms($fd, $rooms);
    }
    
    
    /**
     * 删除FD和房间的绑定关系
     * @param int          $fd FD
     * @param array|string $rooms 房间名称
     */
    public function unbind(int $fd, $rooms = [])
    {
        $allRooms = $this->getRoomsByFd($fd);
        $rooms    = is_array($rooms) ? $rooms : [$rooms];
        $rooms    = count($rooms) ? $rooms : $allRooms;
        
        $removeRooms = [];
        foreach ($rooms as $room) {
            $fds = $this->getFdsByRoom($room);
            
            if (!in_array($fd, $fds)) {
                continue;
            }
            
            $this->setRoomFds($room, array_values(array_diff($fds, [$fd])));
            $removeRooms[] = $room;
        }
        
        $this->setFdRooms($fd, collect($allRooms)->diff($removeRooms)->values()->toArray());
    }
    
    
    /**
     * 通过房间名获取所有加入该房间的FD
     * @param string $room 房间名
     * @return array
     */
    public function getFdsByRoom(string $room) : array
    {
        return $this->getValue($room, WebsocketRoomInterface::ROOMS_KEY) ?? [];
    }
    
    
    /**
     * 通过FD获取该FD加入的所有房间
     * @param int $fd FD
     * @return array
     */
    public function getRoomsByFd(int $fd) : array
    {
        return $this->getValue((string) $fd, WebsocketRoomInterface::DESCRIPTORS_KEY) ?? [];
    }
    
    
    /**
     * 向房间中设置FD
     * @param string $room 房间名
     * @param array  $fds FD
     * @return $this
     */
    protected function setRoomFds(string $room, array $fds)
    {
        return $this->setValue($room, $fds, WebsocketRoomInterface::ROOMS_KEY);
    }
    
    
    /**
     * 向FD中设置房间
     * @param int   $fd FD
     * @param array $rooms 房间名
     * @return $this
     */
    protected function setFdRooms(int $fd, array $rooms)
    {
        return $this->setValue((string) $fd, $rooms, WebsocketRoomInterface::DESCRIPTORS_KEY);
    }
    
    
    /**
     * 设置值到表中
     * @param string $key 键名称
     * @param array  $value 值
     * @param string $table 表名称
     *
     * @return $this
     */
    public function setValue(string $key, array $value, string $table)
    {
        $this->checkTable($table);
        $this->$table->set($key, ['value' => json_encode($value)]);
        
        return $this;
    }
    
    
    /**
     * 在表中获取值
     * @param string $key 键名称
     * @param string $table 表名称
     * @return array|mixed
     */
    public function getValue(string $key, string $table)
    {
        $this->checkTable($table);
        $value = $this->$table->get($key);
        
        return $value ? json_decode($value['value'], true) : [];
    }
    
    
    /**
     * 检测表是否存在
     * @param string $table
     */
    protected function checkTable(string $table)
    {
        if (!property_exists($this, $table) || !$this->$table instanceof SwooleTable) {
            throw new InvalidArgumentException("Invalid table name: `{$table}`.");
        }
    }
}
