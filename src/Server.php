<?php

namespace BusyPHP\swoole;

use think\console\Command;
use think\console\input\Option;

class Server extends Command
{
    public function configure()
    {
        $this->setName('swoole')
            ->addOption('env', 'E', Option::VALUE_REQUIRED, 'Environment name', '')
            ->setDescription('Swoole Server for BusyPHP');
    }
    
    
    public function handle(Manager $manager)
    {
        $this->checkEnvironment();
        
        $this->output->writeln('Starting swoole server...');
        
        $this->output->writeln('You can exit with <info>`CTRL-C`</info>');
        
        $envName = $this->input->getOption('env');
        $manager->start($envName);
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
        
        if (!version_compare(swoole_version(), '4.6.0', 'ge')) {
            $this->output->error('Your Swoole version must be higher than `4.6.0`.');
            
            exit(1);
        }
    }
}