<?php

namespace BusyPHP\swoole\command;

use BusyPHP\swoole\concerns\WithSwooleConfig;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\PhpFile;
use think\console\Command;
use think\helper\Arr;
use BusyPHP\swoole\contract\rpc\RpcParserInterface;
use BusyPHP\swoole\rpc\client\RpcGateway;
use BusyPHP\swoole\rpc\JsonRpcParser;
use function Swoole\Coroutine\run;

class RpcInterface extends Command
{
    use WithSwooleConfig;
    
    public function configure()
    {
        $this->setName('rpc:interface')->setDescription('Generate Rpc Service Interfaces');
    }
    
    
    public function handle()
    {
        run(function() {
            $file = new PhpFile;
            $file->addComment('This file is auto-generated.');
            $file->setStrictTypes();
            $services = [];
            
            $clients = $this->getSwooleConfig('rpc.client', []);
            foreach ($clients as $name => $config) {
                $parserClass = Arr::get($config, 'parser', JsonRpcParser::class);
                /** @var RpcParserInterface $parser */
                $parser = new $parserClass;
                
                $gateway = new RpcGateway($config, $parser);
                
                $result = $gateway->getServices();
                
                $namespace = $file->addNamespace("rpc\\contract\\${name}");
                
                foreach ($result as $interface => $methods) {
                    $services[$name][] = $namespace->getName() . "\\{$interface}";
                    
                    $class = $namespace->addInterface($interface);
                    
                    foreach ($methods as $methodName => ['parameters' => $parameters, 'returnType' => $returnType, 'comment' => $comment]) {
                        $method = $class->addMethod($methodName)
                            ->setVisibility(ClassType::VISIBILITY_PUBLIC)
                            ->setComment(Helpers::unformatDocComment($comment))
                            ->setReturnType($returnType);
                        
                        foreach ($parameters as $parameter) {
                            if ($parameter['type'] && (class_exists($parameter['type']) || interface_exists($parameter['type']))) {
                                $namespace->addUse($parameter['type']);
                            }
                            $param = $method->addParameter($parameter['name'])->setType($parameter['type']);
                            
                            if (array_key_exists('default', $parameter)) {
                                $param->setDefaultValue($parameter['default']);
                            }
                            
                            if (array_key_exists('nullable', $parameter)) {
                                $param->setNullable();
                            }
                        }
                    }
                }
            }
            
            $dumper = new Dumper();
            
            $services = 'return ' . $dumper->dump($services) . ';';
            
            file_put_contents($this->app->getBasePath() . 'rpc.php', $file . $services);
            
            $this->output->writeln('<info>Succeed!</info>');
        });
    }
}
