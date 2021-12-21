<?php

namespace BusyPHP\swoole\event;

use BusyPHP\model\ObjectOption;
use Swoole\Server;
use Swoole\Server\Task;

/**
 * 执行任务事件
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/21 下午6:09 TaskEvent.php $
 * @property Task   $task 任务对象
 * @property Server $server Swoole服务对象
 */
class TaskEvent extends ObjectOption
{
}