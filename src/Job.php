<?php

namespace BusyPHP\swoole;

/**
 * 通用任务Job
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/7 下午8:36 Job.php $
 */
class Job
{
    public $name;
    
    public $params;
    
    
    /**
     * Job constructor.
     * @param array|string $name 类名或类方法数组
     * @param array        $params 初始化参数
     */
    public function __construct($name, $params = [])
    {
        $this->name   = $name;
        $this->params = $params;
    }
    
    
    public function run(\BusyPHP\App $app)
    {
        $job = $this->name;
        if (!is_array($job)) {
            $job = [$job, 'handle'];
        }
        
        [$class, $method] = $job;
        $object = $app->invokeClass($class, $this->params);
        
        return $object->{$method}();
    }
}
