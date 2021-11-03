<?php

namespace BusyPHP\swoole\resetters;

use think\Container;
use BusyPHP\swoole\concerns\ModifyProperty;
use BusyPHP\swoole\Sandbox;
use BusyPHP\swoole\contract\ResetterInterface;

/**
 * Class ResetEvent
 * @package BusyPHP\swoole\resetters
 * @property Container $app;
 */
class ResetEvent implements ResetterInterface
{
    use ModifyProperty;
    
    public function handle(Container $app, Sandbox $sandbox)
    {
        $event = clone $sandbox->getEvent();
        $this->modifyProperty($event, $app);
        $app->instance('event', $event);
        
        return $app;
    }
}
