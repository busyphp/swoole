<?php

namespace BusyPHP\swoole\event;

use BusyPHP\model\ObjectOption;
use Swoole\WebSocket\Frame;

/**
 * 收到Websocket自定义消息事件
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/21 下午5:48 WebsocketEvent.php $
 * @property Frame  $frame 数据帧对象
 * @property string $type 事件名称
 * @property mixed  $data 事件数据
 */
class WebsocketUserEvent extends ObjectOption
{
}