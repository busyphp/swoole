<?php

namespace BusyPHP\swoole\facade;

use think\Facade;

class Server extends Facade
{
    protected static function getFacadeClass()
    {
        return 'swoole.server';
    }
}
