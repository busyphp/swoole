<?php

namespace BusyPHP\swoole\event;

use BusyPHP\model\ObjectOption;

/**
 * Websocket关闭事件
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/21 下午5:58 WebsocketCloseEvent.php $
 * @property int $fd 连接标识符
 * @property int $reactorId 来自哪个 reactor 线程，主动 close 关闭时为负数
 */
class WebsocketCloseEvent extends ObjectOption
{
}