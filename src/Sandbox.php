<?php

namespace BusyPHP\swoole;

use ArrayObject;
use BusyPHP\exception\ClassNotImplementsException;
use BusyPHP\Model as BusyModel;
use Closure;
use InvalidArgumentException;
use ReflectionException;
use ReflectionObject;
use Swoole\Coroutine;
use think\Config;
use think\console\Output;
use think\Container;
use think\Event;
use think\exception\Handle;
use think\Http;
use BusyPHP\swoole\concerns\ModifyProperty;
use BusyPHP\swoole\contract\ResetterInterface;
use BusyPHP\swoole\coroutine\Context;
use BusyPHP\swoole\resetters\ClearInstances;
use BusyPHP\swoole\resetters\ResetConfig;
use BusyPHP\swoole\resetters\ResetEvent;
use BusyPHP\swoole\resetters\ResetService;
use think\Model;
use Throwable;
use BusyPHP\swoole\App as SwooleApp;

/**
 * 沙盒
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/3 下午9:16 Sandbox.php $
 */
class Sandbox
{
    use ModifyProperty;
    
    /**
     * 容器快照
     * @var SwooleApp[]
     */
    protected $snapshots = [];
    
    /**
     * @var SwooleApp
     */
    protected $app;
    
    /**
     * @var Config
     */
    protected $config;
    
    /**
     * @var Event
     */
    protected $event;
    
    /**
     * @var ResetterInterface[]
     */
    protected $resetList = [];
    
    /**
     * @var array
     */
    protected $services = [];
    
    
    /**
     * Sandbox constructor.
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->setBaseApp($app);
        $this->initialize();
    }
    
    
    /**
     * 设置App
     * @param Container $app
     * @return $this
     */
    public function setBaseApp(Container $app)
    {
        $this->app = $app;
        
        return $this;
    }
    
    
    /**
     * 获取APP
     * @return App
     */
    public function getBaseApp()
    {
        return $this->app;
    }
    
    
    /**
     * 初始化
     * @return $this
     */
    protected function initialize()
    {
        Container::setInstance(function() {
            return $this->getApplication();
        });
        
        $this->app->bind(Http::class, \BusyPHP\swoole\Http::class);
        
        $this->setInitialConfig();
        $this->setInitialServices();
        $this->setInitialEvent();
        $this->setInitialResets();
        
        return $this;
    }
    
    
    /**
     * 运行
     * @param Closure $callable 自定义回调
     * @throws ReflectionException
     */
    public function run(Closure $callable)
    {
        $this->init();
        
        try {
            $this->getApplication()->invoke($callable, [$this]);
        } catch (Throwable $e) {
            /** @var Handle $handle */
            $handle = $this->getApplication()->make(Handle::class);
            $handle->renderForConsole(new Output(), $e);
            $handle->report($e);
        } finally {
            $this->clear();
        }
    }
    
    
    /**
     * 初始化容器
     * @throws ReflectionException
     */
    public function init()
    {
        $app = $this->getApplication(true);
        $this->setInstance($app);
        $this->resetApp($app);
    }
    
    
    /**
     * 清理实例
     * @throws ReflectionException
     */
    public function clear()
    {
        if ($app = $this->getSnapshot()) {
            unset($this->snapshots[$this->getSnapshotId()]);
        }
        
        Context::clear();
        $this->setInstance($this->getBaseApp());
    }
    
    
    /**
     * 获取APP对象
     * @param bool $init
     * @return \BusyPHP\App
     */
    public function getApplication($init = false) : \BusyPHP\App
    {
        $snapshot = $this->getSnapshot($init);
        if ($snapshot instanceof Container) {
            return $snapshot;
        }
        
        if ($init) {
            $snapshot = clone $this->getBaseApp();
            $this->setSnapshot($snapshot);
            
            return $snapshot;
        }
        
        throw new InvalidArgumentException('The app object has not been initialized, SnapshotId: ' . $this->getSnapshotId($init) . ', init: ' . ($init ? 'true' : 'false'));
    }
    
    
    /**
     * 获取快照ID
     * @param bool $init
     * @return int
     */
    protected function getSnapshotId($init = false) : int
    {
        if ($init) {
            /** @var ArrayObject $context */
            $context = Coroutine::getContext();
            $context->offsetSet('#root', true);
            
            return Coroutine::getCid();
        } else {
            $cid = Coroutine::getCid();
            
            /** @var ArrayObject $context */
            $context = Coroutine::getContext($cid);
            while (!$context->offsetExists('#root')) {
                $cid = Coroutine::getPcid($cid);
                if ($cid < 1) {
                    break;
                }
            }
            
            return $cid;
        }
    }
    
    
    /**
     * 设置快照
     * @param bool $init
     * @return \BusyPHP\App|null
     */
    public function getSnapshot($init = false)
    {
        return $this->snapshots[$this->getSnapshotId($init)] ?? null;
    }
    
    
    /**
     * 获取快照
     * @param Container $snapshot
     * @return $this
     */
    public function setSnapshot(Container $snapshot) : self
    {
        $this->snapshots[$this->getSnapshotId()] = $snapshot;
        
        return $this;
    }
    
    
    /**
     * @param Container $app
     * @throws ReflectionException
     */
    public function setInstance(Container $app)
    {
        $app->instance('app', $app);
        $app->instance(Container::class, $app);
        
        $reflectObject   = new ReflectionObject($app);
        $reflectProperty = $reflectObject->getProperty('services');
        $reflectProperty->setAccessible(true);
        $services = $reflectProperty->getValue($app);
        
        foreach ($services as $service) {
            $this->modifyProperty($service, $app);
        }
    }
    
    
    /**
     * Set initial config.
     */
    protected function setInitialConfig()
    {
        $this->config = clone $this->getBaseApp()->config;
    }
    
    
    protected function setInitialEvent()
    {
        $this->event = clone $this->getBaseApp()->event;
    }
    
    
    /**
     * 获取配置
     */
    public function getConfig()
    {
        return $this->config;
    }
    
    
    public function getEvent()
    {
        return $this->event;
    }
    
    
    public function getServices()
    {
        return $this->services;
    }
    
    
    /**
     * 初始化服务
     */
    protected function setInitialServices()
    {
        $app = $this->getBaseApp();
        
        $services = $this->config->get('swoole.services', []);
        
        foreach ($services as $service) {
            if (class_exists($service) && !in_array($service, $this->services)) {
                $serviceObj               = new $service($app);
                $this->services[$service] = $serviceObj;
            }
        }
    }
    
    
    /**
     * 初始化重置器
     */
    protected function setInitialResets()
    {
        $app = $this->getBaseApp();
        
        $resetList = [
            ClearInstances::class,
            ResetConfig::class,
            ResetEvent::class,
            ResetService::class,
        ];
        
        $resetList = array_merge($resetList, $this->config->get('swoole.resetters', []));
        
        foreach ($resetList as $reset) {
            $impl = $app->make($reset);
            if (!$impl instanceof ResetterInterface) {
                throw new ClassNotImplementsException($reset, ResetterInterface::class);
            }
            $this->resetList[$reset] = $impl;
        }
    }
    
    
    /**
     * 重置应用
     * @param Container $app
     */
    protected function resetApp(Container $app)
    {
        foreach ($this->resetList as $reset) {
            $reset->handle($app, $this);
        }
        
        Model::setInvoker(function(...$args) {
            return Container::getInstance()->invoke(...$args);
        });
        
        BusyModel::setInvoker(function(...$args) {
            return Container::getInstance()->invoke(...$args);
        });
    }
}
