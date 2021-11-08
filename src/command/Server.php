<?php

namespace BusyPHP\swoole\command;

use BusyPHP\App;
use Swoole\Http\Server as HttpServer;
use Swoole\Server as SwooleServer;
use Swoole\WebSocket\Server as WebsocketServer;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use BusyPHP\swoole\Manager;
use BusyPHP\swoole\PidManager;

/**
 * Swoole HTTP 命令行，支持操作：start|stop|restart|reload
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/5 上午9:56 Server.php $
 * @property App $app
 */
class Server extends Command
{
    public function configure()
    {
        $this->setName('swoole')
            ->addArgument('action', Argument::OPTIONAL, 'start|stop|restart|reload', 'start')
            ->setDescription('Swoole HTTP Server for BusyPHP');
    }
    
    
    protected function initialize(Input $input, Output $output)
    {
        $this->app->bind(SwooleServer::class, function() {
            return $this->createSwooleServer();
        });
        
        $this->app->bind(PidManager::class, function() {
            // 设置 pid 文件地址
            // https://wiki.swoole.com/#/server/setting?id=pid_file
            $pidFile = $this->app->config->get('swoole.server.options.pid_file', '');
            $pidFile = $pidFile ?: $this->app->getRuntimeRootPath('swoole/run.pid');
            $pidDir  = dirname($pidFile);
            if (!is_dir($pidDir)) {
                if (!mkdir($pidDir, 0775, true)) {
                    $this->output->error("Unable to create directory {$pidDir}");
                    
                    exit(1);
                }
            }
            
            return new PidManager($pidFile);
        });
    }
    
    
    public function handle()
    {
        $this->checkEnvironment();
        
        $action = $this->input->getArgument('action');
        
        if (in_array($action, ['start', 'stop', 'reload', 'restart'])) {
            $this->app->invokeMethod([$this, $action], [], true);
        } else {
            $this->output->writeln("<error>Invalid argument action:{$action}, Expected start|stop|restart|reload .</error>");
        }
    }
    
    
    /**
     * 检查环境
     */
    protected function checkEnvironment()
    {
        if (!extension_loaded('swoole')) {
            $this->output->error('Can\'t detect Swoole extension installed.');
            
            exit(1);
        }
        
        if (!version_compare(swoole_version(), '4.4.8', 'ge')) {
            $this->output->error('Your Swoole version must be higher than `4.4.8`.');
            
            exit(1);
        }
    }
    
    
    /**
     * 启动server
     * @access protected
     * @param Manager    $manager
     * @param PidManager $pidManager
     * @return void
     */
    protected function start(Manager $manager, PidManager $pidManager)
    {
        if ($pidManager->isRunning()) {
            $this->output->writeln('<error>swoole http server process is already running.</error>');
            
            return;
        }
        
        $this->output->writeln('Starting swoole http server...');
        
        $host = $manager->getConfig('server.host');
        $port = $manager->getConfig('server.port');
        
        $this->output->writeln("Swoole http server started: <http://{$host}:{$port}>");
        $this->output->writeln('You can exit with <info>`CTRL-C`</info>');
        
        $manager->run();
    }
    
    
    /**
     * 柔性重启server
     * @access protected
     * @param PidManager $manager
     * @return void
     */
    protected function reload(PidManager $manager)
    {
        if (!$manager->isRunning()) {
            $this->output->writeln('<error>no swoole http server process running.</error>');
            
            return;
        }
        
        $this->output->writeln('Reloading swoole http server...');
        
        if (!$manager->killProcess(SIGUSR1)) {
            $this->output->error('> failure');
            
            return;
        }
        
        $this->output->writeln('> success');
    }
    
    
    /**
     * 停止server
     * @access protected
     * @param PidManager $manager
     * @return void
     */
    protected function stop(PidManager $manager)
    {
        if (!$manager->isRunning()) {
            $this->output->writeln('<error>no swoole http server process running.</error>');
            
            return;
        }
        
        $this->output->writeln('Stopping swoole http server...');
        
        $isRunning = $manager->killProcess(SIGTERM, 15);
        
        if ($isRunning) {
            $this->output->error('Unable to stop the swoole_http_server process.');
            
            return;
        }
        
        $this->output->writeln('> success');
    }
    
    
    /**
     * 重启server
     * @access protected
     * @param Manager    $manager
     * @param PidManager $pidManager
     * @return void
     */
    protected function restart(Manager $manager, PidManager $pidManager)
    {
        if ($pidManager->isRunning()) {
            $this->stop($pidManager);
        }
        
        $this->start($manager, $pidManager);
    }
    
    
    /**
     * Create swoole server.
     */
    protected function createSwooleServer()
    {
        $isWebsocket = $this->app->config->get('swoole.websocket.enable', false);
        
        $serverClass = $isWebsocket ? WebsocketServer::class : HttpServer::class;
        $config      = $this->app->config;
        $host        = $config->get('swoole.server.host');
        $port        = $config->get('swoole.server.port');
        $socketType  = $config->get('swoole.server.socket_type', SWOOLE_SOCK_TCP);
        $mode        = $config->get('swoole.server.mode', SWOOLE_PROCESS);
        
        /** @var SwooleServer $server */
        $server = new $serverClass($host, $port, $mode, $socketType);
        
        // 初始化配置
        $options = $config->get('swoole.server.options', '') ?: [];
        
        // 配置 Task 进程的数量，最大值不得超过 swoole_cpu_num() * 1000
        // https://wiki.swoole.com/#/server/setting?id=task_worker_num
        $options['task_worker_num'] = $options['task_worker_num'] ?? swoole_cpu_num();
        
        // 开启静态文件请求处理功能，需配合 document_root 使用
        // https://wiki.swoole.com/#/http_server?id=enable_static_handler
        // https://wiki.swoole.com/#/http_server?id=document_root
        $options['enable_static_handler'] = true;
        $options['document_root']         = $this->app->getRootPath() . 'public' . DIRECTORY_SEPARATOR;
        
        // 日志文件
        // https://wiki.swoole.com/#/server/setting?id=log_file
        $options['log_file'] = $options['log_file'] ?? '';
        $options['log_file'] = $options['log_file'] ?: $this->app->getRuntimeRootPath('swoole/run.log');
        $logDir              = dirname($options['log_file']);
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0775, true)) {
                $this->output->error("Unable to create directory {$logDir}");
                
                exit(1);
            }
        }
        
        $server->set($options);
        
        return $server;
    }
}
