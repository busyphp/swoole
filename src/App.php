<?php

namespace BusyPHP\swoole;

use BusyPHP\swoole\coroutine\Context;

/**
 * Swoole App
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/3 下午下午9:11 App.php $
 */
class App extends \BusyPHP\App
{
    public function runningInConsole() : bool
    {
        return Context::hasData('_fd');
    }
    
    
    public function clearInstances()
    {
        $this->instances = [];
    }
}
