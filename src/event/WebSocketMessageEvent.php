<?php

namespace BusyPHP\swoole\event;

use BusyPHP\model\ObjectOption;
use BusyPHP\swoole\websocket\socketio\EnginePacket;
use Swoole\WebSocket\Frame;

/**
 * 收到Websocket消息事件
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/21 下午5:48 WebSocketMessageEvent.php $
 * @property Frame        $frame 数据帧对象
 * @property EnginePacket $packet socketio 解包后的数据对象
 */
class WebSocketMessageEvent extends ObjectOption
{
}