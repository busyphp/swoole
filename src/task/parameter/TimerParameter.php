<?php

namespace BusyPHP\swoole\task\parameter;

use BusyPHP\App;
use Swoole\Server;

/**
 * onTimer参数
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2019 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2020/11/11 下午10:03 上午 TimerParameter.php $
 */
class TimerParameter
{
    /**
     * 是否异步任务
     * @var bool
     */
    private $async = false;
    
    /**
     * 同步任务是否多任务同时进行
     * @var bool
     */
    private $multi = false;
    
    /**
     * 任务超时时长
     * @var float
     */
    private $timeout = 0.5;
    
    /**
     * 任务数据
     * @var mixed
     */
    private $data = [];
    
    /**
     * 指定要给投递给哪个 Task 进程的进程ID
     * @var int
     */
    private $dstWorkerId = 0;
    
    /**
     * 是否投递数据
     * @var bool
     */
    private $deliver = false;
    
    /**
     * 定时器ID
     * @var int
     */
    public $timeId;
    
    /**
     * 服务对象
     * @var Server
     */
    public $server;
    
    /**
     * @var App
     */
    public $app;
    
    
    /**
     * TaskTimerStruct constructor.
     * @param int    $timeId 定时器ID
     * @param Server $server 服务对象
     * @param App    $app
     */
    public function __construct(int $timeId, Server $server, App $app)
    {
        $this->timeId = $timeId;
        $this->server = $server;
        $this->app    = $app;
    }
    
    
    /**
     * 投递异步任务
     * - 注意：该方法不会阻塞进程。但进程过多会导致worker阻塞。所以要适当配置 config/extend/task.php 中的 max_tasking 和 empty_idle
     * - 建议：该方法一般用于不重要的数据处理。
     * @param mixed $data 投递的任务数据
     * @param int   $dstWorkerId 指定要给投递给哪个 Task 进程
     * <p> - 传入 ID 即可，参考: {@see https://wiki.swoole.com/#/learn?id=taskworker%e8%bf%9b%e7%a8%8b}</p>
     * <p> - 范围参考 {@see https://wiki.swoole.com/#/server/properties?id=worker_id}</p>
     * <p> - 默认为 -1 表示随机投递，底层会自动选择一个空闲 Task 进程</p>
     * @return $this
     */
    public function setAsync($data, int $dstWorkerId = -1) : self
    {
        $this->async       = true;
        $this->multi       = false;
        $this->data        = $data;
        $this->dstWorkerId = $dstWorkerId;
        $this->deliver     = true;
        
        return $this;
    }
    
    
    /**
     * 设置启用同步等待任务。直到执行完毕或者执行超时。
     * - 警告：该方法会阻塞进程。
     * @param mixed $data 投递的任务数据
     * @param float $timeout 任务超时时长控制。单位秒
     * @param int   $dstWorkerId 指定要给投递给哪个 Task 进程
     * <p> - 传入 ID 即可，参考: {@see https://wiki.swoole.com/#/learn?id=taskworker%e8%bf%9b%e7%a8%8b}</p>
     * <p> - 范围参考 {@see https://wiki.swoole.com/#/server/properties?id=worker_id}</p>
     * <p> - 默认为 -1 表示随机投递，底层会自动选择一个空闲 Task 进程</p>
     * @return $this
     */
    public function setSync($data, float $timeout = 0.5, int $dstWorkerId = -1) : self
    {
        $this->async       = false;
        $this->multi       = false;
        $this->data        = $data;
        $this->timeout     = $timeout;
        $this->dstWorkerId = $dstWorkerId;
        $this->deliver     = true;
        
        return $this;
    }
    
    
    /**
     * 设置启用同步等待并发任务。
     * - 系统会将 $data 中的值分配给每一个task中执行，直到执行完毕或者超时。
     * - 警告：该方法会阻塞进程。
     * @param array $data 投递的任务数据，必须是索引数组，数组长度不能超过 1024
     * @param float $timeout 任务超时时长控制。单位秒
     * @return $this
     */
    public function setSyncMulti(array $data, float $timeout = 0.5) : self
    {
        $this->async   = false;
        $this->multi   = true;
        $this->data    = $data;
        $this->timeout = $timeout;
        $this->deliver = true;
        
        return $this;
    }
    
    
    /**
     * 是否投递数据
     * @return bool
     */
    public function isDeliver() : bool
    {
        return $this->deliver;
    }
    
    
    /**
     * 是否异步任务
     * @return bool
     */
    public function isAsync() : bool
    {
        return $this->async;
    }
    
    
    /**
     * 是否同步等待并发任务
     * @return bool
     */
    public function isMulti() : bool
    {
        return $this->multi;
    }
    
    
    /**
     * 获取任务投递的数据
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }
    
    
    /**
     * 获取任务超时时长
     * @return float
     */
    public function getTimeout() : float
    {
        return $this->timeout;
    }
    
    
    /**
     * 获取指定要给投递给哪个 Task 进程ID
     * @return int
     */
    public function getDstWorkerId() : int
    {
        return $this->dstWorkerId;
    }
}