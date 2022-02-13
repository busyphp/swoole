<?php

namespace BusyPHP\swoole\resetters;

use BusyPHP\swoole\concerns\WithSwooleConfig;
use think\Container;
use BusyPHP\swoole\Sandbox;
use BusyPHP\swoole\contract\ResetterInterface;

class ClearInstances implements ResetterInterface
{
    use WithSwooleConfig;
    
    public function handle(Container $app, Sandbox $sandbox)
    {
        $instances = ['log'];
        $instances = array_merge($instances, $this->getSwooleConfig('instances', [], $sandbox->getConfig()));
        
        foreach ($instances as $instance) {
            $app->delete($instance);
        }
        
        return $app;
    }
}
