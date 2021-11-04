<?php

namespace BusyPHP\swoole\app\controller;

use BusyPHP\contract\abstracts\PluginManager;
use Exception;
use think\Response;

/**
 * 插件管理
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/4 下午2:11 ManagerController.php $
 */
class ManagerController extends PluginManager
{
    /**
     * 返回模板路径
     * @return string
     */
    protected function viewPath() : string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR;
    }
    
    
    /**
     * 安装插件
     * @return Response
     * @throws Exception
     */
    public function install() : Response
    {
        return $this->error('');
    }
    
    
    /**
     * 卸载插件
     * @return Response
     * @return Exception
     */
    public function uninstall() : Response
    {
        return $this->error('');
    }
    
    
    /**
     * 设置插件
     * @return Response
     * @return Exception
     */
    public function setting() : Response
    {
        $this->setPageTitle('管理' . $this->info->name);
        
        return $this->display();
    }
}