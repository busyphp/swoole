<?php

namespace BusyPHP\swoole\concerns;

use BusyPHP\App;
use Closure;
use BusyPHP\swoole\App as SwooleApp;
use BusyPHP\swoole\pool\Cache;
use BusyPHP\swoole\pool\Db;
use BusyPHP\swoole\Sandbox;
use ReflectionException;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

/**
 * Trait WithApplication
 * @package BusyPHP\swoole\concerns
 * @property App $container
 */
trait WithApplication
{
    /**
     * @var SwooleApp
     */
    protected $app;
    
    
    /**
     * 初始化应用
     */
    protected function prepareApplication()
    {
        if (!$this->app instanceof SwooleApp) {
            $this->app = new SwooleApp($this->container->getRootPath());
            $this->app->bind(SwooleApp::class, App::class);
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
     * 预加载
     */
    protected function prepareConcretes()
    {
        $defaultConcretes = ['db', 'cache', 'event'];
        
        $concretes = array_merge($defaultConcretes, $this->getConfig('concretes', []));
        
        foreach ($concretes as $concrete) {
            if ($this->app->has($concrete)) {
                $this->app->make($concrete);
            }
        }
    }
    
    
    /**
     * 获取应用
     * @return App
     */
    protected function getApplication()
    {
        return $this->app;
    }
    
    
    /**
     * 获取沙箱
     * @return Sandbox
     */
    protected function getSandbox() : Sandbox
    {
        return $this->app->make(Sandbox::class);
    }
    
    
    /**
     * 在沙箱中执行
     * @param Closure $callable
     */
    public function runInSandbox(Closure $callable)
    {
        try {
            $this->getSandbox()->run($callable);
        } catch (ReflectionException $e) {
        }
    }
    
    
    /**
     * 在协程中运行
     * @param callable $func
     * @param          ...$params
     * @return void
     */
    public function runWithBarrier(callable $func, ...$params)
    {
        $channel = new Channel(1);
        
        Coroutine::create(function(...$params) use ($channel, $func) {
            call_user_func_array($func, $params);
            
            $channel->close();
        }, ...$params);
        
        $channel->pop();
    }
}
