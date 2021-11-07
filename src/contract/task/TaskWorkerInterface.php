<?php

namespace BusyPHP\swoole\contract\task;

use BusyPHP\swoole\task\parameter\FinishParameter;
use BusyPHP\swoole\task\parameter\TaskParameter;
use BusyPHP\swoole\task\parameter\TimerParameter;

/**
 * 任务接口类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/6 下午10:39 TaskWorkerInterface.php $
 */
interface TaskWorkerInterface
{
    /**
     * 定时任务间隔毫秒
     * @return int
     */
    public static function getTimerIntervalMs() : int;
    
    
    /**
     * 没有空闲进程的时候是否进行任务投递
     * - 建议: false
     * @return bool
     */
    public static function getTaskEmptyIdleStatus() : bool;
    
    
    /**
     * 任务投递允许的排队数，0则不限制
     * - 建议: 0
     * @return int
     */
    public static function getTaskMaxNumber() : int;
    
    
    /**
     * 执行定时任务
     * - 该方法一般用于不耗时的任务处理。
     * - 处理耗时任务，建议在该方法中获取处理数据后投递到 task 中执行，不会阻塞定时器。
     * - 注意：如果该方法耗时超过下一次计时，会导致下一次计时被系统丢弃；
     * - 警告：投递数据超过 8K 时会启用临时文件来保存。
     * - 当临时文件内容超过 server->package_max_length 时底层会抛出一个警告。此警告不影响数据的投递
     * - 过大的 Task 可能会存在性能问题；
     * @param TimerParameter $parameter
     */
    public static function onTimer(TimerParameter $parameter) : void;
    
    
    /**
     * 执行投递任务
     * - 注意: 执行完成后返回执行成功的数据
     * - 注意: 请不要使用 sleep/usleep/time_sleep_until/time_nanosleep 函数，
     *   参见: {@link https://wiki.swoole.com/#/getting_started/notice?id=sleepusleep%e7%9a%84%e5%bd%b1%e5%93%8d}
     * @param TaskParameter $parameter
     * @return bool true执行成功，false执行失败
     */
    public static function onTask(TaskParameter $parameter) : bool;
    
    
    /**
     * 投递任务执行完毕
     * @param FinishParameter $parameter
     */
    public static function onFinish(FinishParameter $parameter) : void;
}