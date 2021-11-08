<?php

namespace BusyPHP\swoole;

use Closure;
use Exception;
use InvalidArgumentException;
use ReflectionException;
use ReflectionObject;
use RuntimeException;
use think\Config;
use think\Container;
use think\Event;
use think\Http;
use BusyPHP\swoole\concerns\ModifyProperty;
use BusyPHP\swoole\contract\ResetterInterface;
use BusyPHP\swoole\coroutine\Context;
use BusyPHP\swoole\resetters\ClearInstances;
use BusyPHP\swoole\resetters\ResetConfig;
use BusyPHP\swoole\resetters\ResetEvent;
use BusyPHP\swoole\resetters\ResetService;
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
    protected $resetters = [];
    
    protected $services  = [];
    
    
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
        $this->setInitialResetters();
        
        return $this;
    }
    
    
    /**
     * 运行
     * @param Closure $callable 自定义回调
     * @param int     $fd 容器标识
     * @param bool    $persistent 持久化
     * @throws Throwable
     */
    public function run(Closure $callable, $fd = null, $persistent = false)
    {
        $this->init($fd);
        
        try {
            $this->getApplication()->invoke($callable, [$this]);
        } catch (Throwable $e) {
            throw $e;
        } finally {
            $this->clear(!$persistent);
        }
    }
    
    
    /**
     * 初始化容器
     * @param mixed $fd 容器标识
     * @throws ReflectionException
     */
    public function init($fd = null)
    {
        if (!is_null($fd)) {
            Context::setData('_fd', $fd);
        }
        $app = $this->getApplication(true);
        $this->setInstance($app);
        $this->resetApp($app);
    }
    
    
    /**
     * 清空
     * @param bool $snapshot
     * @throws Exception
     */
    public function clear($snapshot = true)
    {
        if ($snapshot && $this->getSnapshot()) {
            unset($this->snapshots[$this->getSnapshotId()]);
            
            // 垃圾回收
            $divisor     = $this->config->get('swoole.gc.divisor', 100);
            $probability = $this->config->get('swoole.gc.probability', 1);
            if (random_int(1, $divisor) <= $probability) {
                gc_collect_cycles();
            }
        }
        
        Context::clear();
        $this->setInstance($this->getBaseApp());
    }
    
    
    public function getApplication($init = false)
    {
        $snapshot = $this->getSnapshot();
        if ($snapshot instanceof Container) {
            return $snapshot;
        }
        
        if ($init) {
            $snapshot = clone $this->getBaseApp();
            $this->setSnapshot($snapshot);
            
            return $snapshot;
        }
        throw new InvalidArgumentException('The app object has not been initialized');
    }
    
    
    protected function getSnapshotId()
    {
        if ($fd = Context::getData('_fd')) {
            return 'fd_' . $fd;
        }
        
        return Context::getCoroutineId();
    }
    
    
    /**
     * Get current snapshot.
     * @return \BusyPHP\App|null
     */
    public function getSnapshot()
    {
        return $this->snapshots[$this->getSnapshotId()] ?? null;
    }
    
    
    public function setSnapshot(Container $snapshot)
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
    protected function setInitialResetters()
    {
        $app = $this->getBaseApp();
        
        $resetters = [
            ClearInstances::class,
            ResetConfig::class,
            ResetEvent::class,
            ResetService::class,
        ];
        
        $resetters = array_merge($resetters, $this->config->get('swoole.resetters', []));
        
        foreach ($resetters as $resetter) {
            $resetterClass = $app->make($resetter);
            if (!$resetterClass instanceof ResetterInterface) {
                throw new RuntimeException("{$resetter} must implement " . ResetterInterface::class);
            }
            $this->resetters[$resetter] = $resetterClass;
        }
    }
    
    
    /**
     * 重置应用
     * @param Container $app
     */
    protected function resetApp(Container $app)
    {
        foreach ($this->resetters as $resetter) {
            $resetter->handle($app, $this);
        }
    }
    
    
    /**
     * 生成Fd
     * @param string $prefix
     * @param mixed  ...$str
     * @return string
     */
    public static function createFd(string $prefix, ...$str) : string
    {
        return $prefix . md5(implode(',', $str));
    }
}
