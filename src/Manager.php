<?php

namespace BusyPHP\swoole;

use think\Event;
use think\exception\Handle;
use think\Http;
use think\swoole\Manager as ThinkManager;
use think\swoole\pool\Cache;
use Throwable;

/**
 *
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/3 下午上午11:57 Manager.php $
 * @method App getApplication()
 */
class Manager extends ThinkManager
{
    /**
     * 覆盖 {@see \think\swoole\App} 为 {@see \BusyPHP\swoole\App}
     * 覆盖 {@see \think\swoole\Db} 为 {@see \BusyPHP\swoole\Db}
     * @param string $envName
     */
    protected function prepareApplication(string $envName)
    {
        if (!$this->app instanceof App) {
            $this->app = new App($this->container->getRootPath());
            $this->app->setEnvName($envName);
            $this->app->bind(App::class, \think\App::class);
            $this->app->bind(Manager::class, $this);
            //绑定连接池
            if ($this->getConfig('pool.db.enable', true)) {
                $this->app->bind('db', Db::class);
                $this->app->resolving(Db::class, function(Db $db) {
                    $db->setLog($this->container->log);
                });
            }
            if ($this->getConfig('pool.cache.enable', true)) {
                $this->app->bind('cache', Cache::class);
            }
            $this->app->initialize();
            $this->prepareConcretes();
        }
    }
    
    
    /**
     * 覆盖 {@see \think\swoole\App} 为 {@see \BusyPHP\swoole\App}
     * @inheritDoc
     */
    public function onRequest($req, $res)
    {
        $this->runWithBarrier([$this, 'runInSandbox'], function(Http $http, Event $event, App $app) use ($req, $res) {
            $app->setInConsole(false);
            
            $request = $this->prepareRequest($req);
            
            $_SERVER['VAR_DUMPER_FORMAT'] = 'html';
            $_SERVER['SERVER_SOFTWARE']   = 'Swoole ' . swoole_version();
            try {
                $response = $this->handleRequest($http, $request);
            } catch (Throwable $e) {
                $response = $this->app->make(Handle::class)->render($request, $e);
            } finally {
                unset($_SERVER['VAR_DUMPER_FORMAT']);
            }
            
            $this->sendResponse($res, $response, $app->cookie);
        });
    }
}