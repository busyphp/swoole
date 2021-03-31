<?php

namespace BusyPHP\swoole;

use BusyPHP\swoole\app\controller\InstallController;
use BusyPHP\swoole\app\controller\ManagerController;
use think\Route;

/**
 * Server
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2019 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2020/6/18 下午10:45 上午 Service.php $
 */
class Service extends \think\Service
{
    use SwooleConfig;
    
    public function boot()
    {
        // 注册管理路由
        $this->registerRoutes(function(Route $route) {
            $route->rule('general/plugin/install/swoole', InstallController::class . '@index');
            
            
            // 后台路由
            if ($this->app->http->getName() === 'admin') {
                $prefix = $this->getSwooleConfig('admin.manager.menu.action_prefix', 'swoole');
                
                
                $route->rule("System.Index/{$prefix}_index", ManagerController::class . '@index')->append([
                    'group'   => 'System',
                    'control' => 'Index',
                    'action'  => "{$prefix}_index",
                    'type'    => 'plugin'
                ]);
                
                $route->rule("System.Index/{$prefix}_reload", ManagerController::class . '@reload')->append([
                    'group'   => 'System',
                    'control' => 'Index',
                    'action'  => "{$prefix}_reload",
                    'type'    => 'plugin'
                ]);
                
                $route->rule("System.Index/{$prefix}_stats", ManagerController::class . '@stats')->append([
                    'group'   => 'System',
                    'control' => 'Index',
                    'action'  => "{$prefix}_stats",
                    'type'    => 'plugin'
                ]);
                
                $route->mergeRuleRegex(true);
            }
        });
        
        $this->commands(Server::class);
    }
}
