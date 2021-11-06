<?php

namespace BusyPHP\swoole\app\module;

use BusyPHP\helper\TransHelper;
use BusyPHP\swoole\contract\BaseGateway;

/**
 * 插件管理网关
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/6 下午8:19 ManagerGateway.php $
 */
class ManagerGateway extends BaseGateway
{
    /**
     * 获取运行状态信息
     * @return array
     */
    public function stats()
    {
        if ($this->canRun()) {
            $stats = $this->server()->stats();
            
            $stats['format_start_time'] = TransHelper::date($stats['start_time']);
            $stats['continue_second']   = time() - $stats['start_time'];
            $stats['format_continue']   = $this->getTimeHour($stats['continue_second']);
            
            return $stats;
        } else {
            return $this->gateway(__FUNCTION__, func_get_args());
        }
    }
    
    
    /**
     * 软重载
     */
    public function reload()
    {
        if ($this->canRun()) {
            $this->server()->reload();
        } else {
            $this->gateway(__FUNCTION__, func_get_args());
        }
    }
    
    
    /**
     * 时长转换
     * @param string $second 秒数
     * @return string
     */
    private function getTimeHour($second)
    {
        if ($second <= 60) {
            return $second . '秒';
        }
        
        $day     = floor($second / (3600 * 24));
        $day     = $day > 0 ? $day . '天' : '';
        $hour    = floor(($second % (3600 * 24)) / 3600);
        $hour    = $hour > 0 ? $hour . '小时' : '';
        $minutes = floor((($second % (3600 * 24)) % 3600) / 60);
        
        if ($minutes > 0) {
            $minutes = $minutes > 0 ? $minutes . '分钟' : '';
            
            return $day . $hour . $minutes;
        }
        
        if ($hour) {
            return $day . $hour;
        }
        
        return $day;
    }
}