<?php

namespace BusyPHP\swoole;

use BusyPHP\Request;
use BusyPHP\Service as BaseService;
use BusyPHP\swoole\app\controller\IndexController;
use BusyPHP\swoole\command\Rpc;
use BusyPHP\swoole\command\RpcInterface;
use BusyPHP\swoole\command\Server as ServerCommand;
use BusyPHP\swoole\concerns\WithSwooleConfig;
use Closure;
use think\Response;
use think\Route;

/**
 * 服务类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/3 下午9:14 Service.php $
 */
class Service extends \think\Service
{
    use WithSwooleConfig;
    
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
        
        // 不公开服务
        if (!$this->getSwooleConfig('server.public', false)) {
            $this->app->middleware->add(function(Request $request, Closure $next) {
                if ($this->app instanceof App) {
                    $mode = [];
                    if ($this->getSwooleConfig('websocket.server.enable', false)) {
                        $mode[] = 'Websocket';
                    } else {
                        $mode[] = 'Http';
                    }
                    if ($this->getSwooleConfig('rpc.server.enable', false)) {
                        $mode[] = 'Rpc';
                    }
                    if ($this->getSwooleConfig('tcp.server.enable', false)) {
                        $mode[] = 'Tcp';
                    }
                    
                    $message = 'This is [ ';
                    $message .= implode(', ', $mode);
                    $message .= ' ] services <hr />' . date('Y-m-d H:i:s');
                    
                    return Response::create("<center>{$message}</center>", 'html', 200);
                }
                
                return $next($request);
            });
        }
        
        $this->commands(ServerCommand::class, RpcInterface::class, Rpc::class);
    }
}
