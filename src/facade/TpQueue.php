<?php

namespace BusyPHP\swoole\facade;

use think\Facade;
use think\Queue;

/**
 * ThinkQueue工厂类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/9 上午12:17 Queue.php $
 * @method static int size($queue = null) 获取队列长度
 * @method static string|int pushOn($queue, $job, $data = '')
 * @method static string|int push($job, $data = '', $queue = null)
 * @method static string|int pushRaw($payload, $queue = null, array $options = [])
 * @method static string|int later($delay, $job, $data = '', $queue = null)
 * @method static string|int laterOn($queue, $delay, $job, $data = '')
 * @method static mixed bulk($jobs, $data = '', $queue = null)
 * @method static mixed pop($queue = null)
 */
class TpQueue extends Facade
{
    protected static function getFacadeClass()
    {
        return Queue::class;
    }
}