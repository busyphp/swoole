<?php

namespace BusyPHP\swoole\contract\timer;

/**
 * 计时器接口
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/8 上午10:59 TimerInterface.php $
 */
interface TimerInterface
{
    /**
     * 执行间隔毫秒数
     * @return int
     */
    public static function onTimerGetMillisecond() : int;
    
    
    /**
     * 执行并发数
     * @return int
     */
    public static function onTimerGetConcurrency() : int;
    
    
    /**
     * 执行模式，true阻塞，false不阻塞
     * @return bool
     */
    public static function onTimerGetMode() : bool;
    
    
    /**
     * 执行定时任务
     * @param TimerParameter $parameter
     */
    public static function onTimerRun(TimerParameter $parameter);
}