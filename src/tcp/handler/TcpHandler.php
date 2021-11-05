<?php

namespace BusyPHP\swoole\tcp\handler;

use BusyPHP\swoole\contract\tcp\TcpHandlerInterface;
use Swoole\Server;

/**
 * 默认TCP事件处理器
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/5 上午10:51 TcpHandle.php $
 */
class TcpHandler implements TcpHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function onConnect(Server $server, int $fd, int $reactorId) : ?bool
    {
        return null;
    }
    
    
    /**
     * @inheritDoc
     */
    public function onReceive(Server $server, int $fd, int $reactorId, $data) : ?bool
    {
        return null;
    }
    
    
    /**
     * @inheritDoc
     */
    public function onClose(Server $server, int $fd, int $reactorId) : ?bool
    {
        return null;
    }
}