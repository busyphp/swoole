<?php

namespace BusyPHP\swoole;

use BusyPHP\Request;
use ReflectionException;
use think\Http as ThinkHttp;
use think\Middleware;
use think\Response;
use think\Route;
use BusyPHP\swoole\concerns\ModifyProperty;

/**
 * Swoole Http
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/3 下午9:17 Http.php $
 */
class Http extends ThinkHttp
{
    use ModifyProperty;
    
    /**
     * @var Middleware
     */
    protected static $middleware;
    
    /**
     * @var Route[]
     */
    protected static $routes = [];
    
    
    /**
     * 加载中间件
     * @throws ReflectionException
     */
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
    
    
    /**
     * 调度路由
     * @param Request $request
     * @return Response
     * @throws ReflectionException
     */
    protected function dispatchToRoute($request)
    {
        if (isset(self::$routes[$this->name])) {
            $newRoute = clone self::$routes[$this->name];
            $this->modifyProperty($newRoute, $this->app);
            $this->app->instance("route", $newRoute);
        }
        
        return parent::dispatchToRoute($request);
    }
    
    
    /**
     * 加载路由
     * @throws ReflectionException
     */
    protected function loadRoutes() : void
    {
        if (!isset(self::$routes[$this->name])) {
            $this->app->route->clear();
            parent::loadRoutes();
            
            self::$routes[$this->name] = clone $this->app->route;
            $this->modifyProperty(self::$routes[$this->name], null);
            $this->modifyProperty(self::$routes[$this->name], null, 'request');
        }
    }
}
