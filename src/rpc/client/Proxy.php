<?php

namespace BusyPHP\swoole\rpc\client;

use BusyPHP\App;
use InvalidArgumentException;
use Nette\PhpGenerator\Factory;
use Nette\PhpGenerator\PhpNamespace;
use ReflectionClass;
use BusyPHP\swoole\Middleware;
use BusyPHP\swoole\rpc\Protocol;

abstract class Proxy
{
    protected $interface;
    
    /** @var RpcGateway */
    protected $gateway;
    
    /** @var App */
    protected $app;
    
    protected $middleware = [];
    
    
    final public function __construct(App $app, RpcGateway $gateway, $middleware)
    {
        $this->app        = $app;
        $this->gateway    = $gateway;
        $this->middleware = $middleware;
    }
    
    
    final protected function proxyCall($method, $params)
    {
        $protocol = Protocol::make($this->interface, $method, $params);
        
        return Middleware::make($this->app, $this->middleware)
            ->pipeline()
            ->send($protocol)
            ->then(function(Protocol $protocol) {
                return $this->gateway->call($protocol);
            });
    }
    
    
    final public static function getClassName($client, $interface)
    {
        if (!interface_exists($interface)) {
            throw new InvalidArgumentException(sprintf('%s must be exist interface!', $interface));
        }
        
        $proxyName = class_basename($interface) . "Service";
        $className = "rpc\\service\\{$client}\\{$proxyName}";
        
        if (!class_exists($className, false)) {
            $namespace = new PhpNamespace("rpc\\service\\{$client}");
            $namespace->addUse(Proxy::class);
            $namespace->addUse($interface);
            
            $class = $namespace->addClass($proxyName);
            
            $class->setExtends(Proxy::class);
            $class->addImplement($interface);
            $class->addProperty('interface', class_basename($interface));
            
            $reflection = new ReflectionClass($interface);
            
            foreach ($reflection->getMethods() as $methodRef) {
                $method = (new Factory)->fromMethodReflection($methodRef);
                $body   = "\$this->proxyCall('{$methodRef->getName()}', func_get_args());";
                if ($method->getReturnType() != 'void') {
                    $body = "return {$body}";
                }
                $method->setBody($body);
                $class->addMember($method);
            }
            
            eval($namespace);
        }
        
        return $className;
    }
}
