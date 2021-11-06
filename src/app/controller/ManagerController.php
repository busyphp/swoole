<?php

namespace BusyPHP\swoole\app\controller;

use BusyPHP\app\admin\model\system\plugin\SystemPlugin;
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
     * 创建表SQL
     * @var string[]
     */
    private $createTableSql = [
        'jobs' => "CREATE TABLE `#__table_prefix__#jobs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `queue` VARCHAR(255) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `attempts` TINYINT(3) UNSIGNED NOT NULL,
    `reserve_time` INT(11) UNSIGNED DEFAULT NULL,
    `available_time` INT(11) UNSIGNED NOT NULL,
    `create_time` INT(11) UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    KEY `queue` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Swoole队列表'",
        
        'failed_jobs' => "CREATE TABLE `#__table_prefix__#failed_jobs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `connection` TEXT NOT NULL,
    `queue` TEXT NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `exception` LONGTEXT NOT NULL,
    `fail_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Swoole队列失败表'",
    ];
    
    /**
     * 删除表SQL
     * @var string[]
     */
    private $deleteTableSql = [
        "DROP TABLE IF EXISTS `#__table_prefix__#jobs`",
        "DROP TABLE IF EXISTS `#__table_prefix__#failed_jobs`",
    ];
    
    
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
        $model = SystemPlugin::init();
        $model->startTrans();
        try {
            foreach ($this->deleteTableSql as $item) {
                $this->executeSQL($item);
            }
            
            foreach ($this->createTableSql as $item) {
                $this->executeSQL($item);
            }
            
            $model->setInstall($this->info->package);
            
            $model->commit();
        } catch (Exception $e) {
            $model->rollback();
            
            throw $e;
        }
        
        $this->updateCache();
        $this->logInstall();
        
        return $this->success('安装成功');
    }
    
    
    /**
     * 卸载插件
     * @return Response
     * @throws Exception
     */
    public function uninstall() : Response
    {
        $model = SystemPlugin::init();
        $model->startTrans();
        try {
            foreach ($this->deleteTableSql as $item) {
                $this->executeSQL($item);
            }
            
            $model->setUninstall($this->info->package);
            
            $model->commit();
        } catch (Exception $e) {
            $model->rollback();
            
            throw $e;
        }
        
        $this->updateCache();
        $this->logUninstall();
        
        return $this->success('卸载成功');
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