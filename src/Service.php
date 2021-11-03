<?php

namespace BusyPHP\swoole;

use BusyPHP\swoole\command\Queue;
use BusyPHP\swoole\command\Rpc;
use BusyPHP\swoole\command\RpcInterface;
use BusyPHP\swoole\command\Server as ServerCommand;

/**
 * 服务类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/3 下午9:14 Service.php $
 */
class Service extends \think\Service
{
    public function boot()
    {
        $this->commands(ServerCommand::class, RpcInterface::class, Rpc::class, Queue::class);
    }
}
