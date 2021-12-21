<?php

namespace BusyPHP\swoole\event;

use BusyPHP\model\ObjectOption;
use BusyPHP\Request;

/**
 * WebSocket已连接事件
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/21 下午5:46 WebSocketOpenEvent.php $
 * @property int     $fd 连接标识符
 * @property Request $request 请求对象
 */
class WebSocketOpenEvent extends ObjectOption
{
}