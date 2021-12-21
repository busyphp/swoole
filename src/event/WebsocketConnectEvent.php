<?php

namespace BusyPHP\swoole\event;

use BusyPHP\model\ObjectOption;

/**
 * WebSocket连接事件
 * 针对 socket.id 的事件
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/21 下午5:57 WebsocketConnectEvent.php $
 * @property int   $fd 连接标识符
 * @property mixed $data 数据
 */
class WebsocketConnectEvent extends ObjectOption
{
}