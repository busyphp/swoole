<?php

namespace BusyPHP\swoole;

/**
 * Server
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2019 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2020/6/18 下午10:45 上午 Service.php $
 */
class Service extends \think\Service
{
    public function boot()
    {
        $this->commands(Server::class);
    }
}
