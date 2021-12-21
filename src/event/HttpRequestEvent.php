<?php

namespace BusyPHP\swoole\event;

use BusyPHP\model\ObjectOption;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * Http请求事件
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/21 下午6:04 HttpRequestEvent.php $
 * @property Request  $request 请求对象
 * @property Response $response 响应对象
 */
class HttpRequestEvent extends ObjectOption
{
}