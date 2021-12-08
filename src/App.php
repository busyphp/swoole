<?php

namespace BusyPHP\swoole;

/**
 * Swoole App 基本类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/3 下午下午9:11 App.php $
 */
class App extends \BusyPHP\App
{
    protected $inConsole = true;
    
    
    /**
     * 设置是否运行在CLI模式中
     * @param bool $inConsole
     */
    public function setInConsole($inConsole = true)
    {
        $this->inConsole = $inConsole;
    }
    
    
    /**
     * 是否运行在命令行下
     * @return bool
     */
    public function runningInConsole() : bool
    {
        return $this->inConsole;
    }
}
