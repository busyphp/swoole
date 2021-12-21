<?php

namespace BusyPHP\swoole\event;

use BusyPHP\model\ObjectOption;
use Swoole\WebSocket\Frame;

/**
 * WebSocket断开连接事件
 * 针对 socket.id 的事件
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/21 下午5:57 WebsocketDisconnectEvent.php $
 * @property Frame $frame 数据帧包
 */
class WebsocketDisconnectEvent extends ObjectOption
{
}