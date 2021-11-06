<?php

namespace BusyPHP\swoole\app\controller;

use BusyPHP\app\admin\controller\AdminController;
use BusyPHP\swoole\app\module\ManagerGateway;
use think\Response;

/**
 * Swoole管理
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/4 下午2:12 IndexController.php $
 */
class IndexController extends AdminController
{
    /**
     * 获取运行信息
     * @return Response
     */
    public function info()
    {
        return $this->success(ManagerGateway::init()->stats());
    }
    
    
    /**
     * 软重载
     * @return Response
     */
    public function reload()
    {
        ManagerGateway::init()->reload();
        
        return $this->success('刷新成功');
    }
}