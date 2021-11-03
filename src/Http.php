<?php

namespace BusyPHP\swoole;

use think\Middleware;
use think\Route;
use BusyPHP\swoole\concerns\ModifyProperty;

/**
 * Swoole Http
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/3 下午9:17 Http.php $
 * @property $request
 */
class Http extends \think\Http
{
    use ModifyProperty;
    
    /** @var Middleware */
    protected static $middleware;
    
    /** @var Route */
    protected static $route;
    
    
    protected function loadMiddleware() : void
    {
        if (!isset(self::$middleware)) {
            parent::loadMiddleware();
            self::$middleware = clone $this->app->middleware;
            $this->modifyProperty(self::$middleware, null);
        }
        
        $middleware = clone self::$middleware;
        $this->modifyProperty($middleware, $this->app);
        $this->app->instance("middleware", $middleware);
    }
    
    
    protected function loadRoutes() : void
    {
        if (!isset(self::$route)) {
            parent::loadRoutes();
            self::$route = clone $this->app->route;
            $this->modifyProperty(self::$route, null);
            $this->modifyProperty(self::$route, null, 'request');
        }
    }
    
    
    protected function dispatchToRoute($request)
    {
        if (isset(self::$route)) {
            $newRoute = clone self::$route;
            $this->modifyProperty($newRoute, $this->app);
            $this->app->instance("route", $newRoute);
        }
        
        return parent::dispatchToRoute($request);
    }
}
