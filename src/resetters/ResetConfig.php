<?php

namespace BusyPHP\swoole\resetters;

use think\Container;
use BusyPHP\swoole\Sandbox;
use BusyPHP\swoole\contract\ResetterInterface;

class ResetConfig implements ResetterInterface
{

    public function handle(Container $app, Sandbox $sandbox)
    {
        $app->instance('config', clone $sandbox->getConfig());

        return $app;
    }
}
