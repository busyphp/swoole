<?php

namespace BusyPHP\swoole;

use think\swoole\coroutine\Context;

class App extends \BusyPHP\App
{
    public function runningInConsole()
    {
        return Context::hasData('_fd');
    }
}
