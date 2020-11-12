<?php

namespace BusyPHP\swoole\app\controller;

use BusyPHP\app\admin\model\system\menu\SystemMenu;
use BusyPHP\app\admin\model\system\menu\SystemMenuField;
use BusyPHP\Controller;
use BusyPHP\exception\AppException;
use BusyPHP\helper\util\Arr;
use BusyPHP\swoole\SwooleConfig;

/**
 * 安装
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2019 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2020/10/7 下午5:41 下午 InstallController.php $
 */
class InstallController extends Controller
{
    use SwooleConfig;
    
    public function index()
    {
        $actionPrefix = $this->getSwooleConfig('admin.manager.menu.action_prefix', 'swoole');
        $actionsList  = [
            [
                'name'    => '守护程序管理',
                'action'  => $actionPrefix . '_index',
                'icon'    => 'tachometer',
                'is_show' => 1,
            ],
            [
                'name'    => '柔性重启守护程序',
                'action'  => $actionPrefix . '_reload',
                'icon'    => '',
                'is_show' => 0
            ],
        ];
        $actions      = Arr::listByKey($actionsList, 'action');
        $actions      = array_keys($actions);
        
        
        try {
            $db = SystemMenu::init();
            
            // 是否安装过该菜单
            $where          = SystemMenuField::init();
            $where->action  = ['in', [$actions]];
            $where->control = 'index';
            $where->module  = 'system';
            if ($db->whereof($where)->findData()) {
                throw new AppException('您已安装过该插件，请勿重复安装');
            }
            
            // 插入菜单数据
            foreach ($actionsList as $r) {
                $dataSQL = <<<SQL
INSERT INTO `busy_system_menu` (`name`, `action`, `control`, `module`, `pattern`, `params`, `higher`, `icon`, `link`, `target`, `is_default`, `is_show`, `is_disabled`, `is_has_action`, `is_system`, `sort`) VALUES
    ('{$r['name']}', '{$r['action']}', 'index', 'system', '', '', '', '{$r['icon']}', '', '', 0, {$r['is_show']}, 0, 1, 0, 50)
SQL;
                $db->execute($dataSQL);
            }
            
            
            return $this->success('安装成功', '/');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), '/');
        }
    }
}