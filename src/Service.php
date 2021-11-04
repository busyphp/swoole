<?php

namespace BusyPHP\swoole;

use BusyPHP\Service as BaseService;
use BusyPHP\swoole\app\controller\IndexController;
use BusyPHP\swoole\command\Queue;
use BusyPHP\swoole\command\Rpc;
use BusyPHP\swoole\command\RpcInterface;
use BusyPHP\swoole\command\Server as ServerCommand;
use think\Route;

/**
 * 服务类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/3 下午9:14 Service.php $
 */
class Service extends \think\Service
{
    public function boot()
    {
        $this->registerRoutes(function(Route $route) {
            $actionPattern = '<' . BaseService::ROUTE_VAR_ACTION . '>';
            
            // 后台路由
            if ($this->app->http->getName() === 'admin') {
                $route->rule("plugins_swoole/{$actionPattern}", IndexController::class . "@{$actionPattern}")->append([
                    BaseService::ROUTE_VAR_TYPE    => 'plugin',
                    BaseService::ROUTE_VAR_CONTROL => 'plugins_swoole',
                ]);
            }
        });
        
        $this->commands(ServerCommand::class, RpcInterface::class, Rpc::class, Queue::class);
    }
}
