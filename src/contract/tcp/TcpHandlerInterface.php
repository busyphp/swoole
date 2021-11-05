<?php

namespace BusyPHP\swoole\contract\tcp;

use Swoole\Server;

/**
 * TCP事件处理接口类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/5 上午10:43 TcpHandleInterface.php $
 */
interface TcpHandlerInterface
{
    /**
     * 客户端于TCP建立连接事件
     * @param Server $server
     * @param int    $fd 客户端ID
     * @param int    $reactorId
     * @return bool|null 返回true阻止 swoole.tcp.connect 事件
     */
    public function onConnect(Server $server, int $fd, int $reactorId) : ?bool;
    
    
    /**
     * 收到客户端消息事件
     * @param Server $server
     * @param int    $fd 客户端ID
     * @param int    $reactorId
     * @param string $data
     * @return bool|null 返回true阻止 swoole.tcp.receive 事件
     */
    public function onReceive(Server $server, int $fd, int $reactorId, $data) : ?bool;
    
    
    /**
     * 收到客户端关闭连接的事件
     * @param Server $server
     * @param int    $fd 客户端ID
     * @param int    $reactorId
     * @return bool|null 返回true阻止 swoole.tcp.close 事件
     */
    public function onClose(Server $server, int $fd, int $reactorId) : ?bool;
}