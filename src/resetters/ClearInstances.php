<?php

namespace BusyPHP\swoole\resetters;

use think\Container;
use BusyPHP\swoole\Sandbox;
use BusyPHP\swoole\contract\ResetterInterface;

class ClearInstances implements ResetterInterface
{
    public function handle(Container $app, Sandbox $sandbox)
    {
        $instances = ['log'];
        
        $instances = array_merge($instances, $sandbox->getConfig()->get('swoole.instances', []));
        
        foreach ($instances as $instance) {
            $app->delete($instance);
        }
        
        return $app;
    }
}
