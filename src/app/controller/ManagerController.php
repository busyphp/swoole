<?php

namespace BusyPHP\swoole\app\controller;

use BusyPHP\app\admin\controller\AdminCurdController;
use BusyPHP\exception\AppException;
use BusyPHP\helper\net\Http;
use BusyPHP\helper\util\Transform;
use BusyPHP\swoole\Manager;
use BusyPHP\swoole\SwooleConfig;

/**
 * 任务管理面板
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2019 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2020/6/17 下午4:32 下午 ManagerController.php $
 */
class ManagerController extends AdminCurdController
{
    use SwooleConfig;
    
    private $isSwoole = false;
    
    private $actionPrefix;
    
    
    public function initialize($checkLogin = true)
    {
        parent::initialize($checkLogin);
        
        $this->isSwoole = $this->iParam('swoole', 'intval') > 0;
        
        $this->actionPrefix = $this->getAdminConfig('menu.action_prefix', 'swoole');
    }
    
    
    /**
     * 任务面板主页
     */
    public function index()
    {
        $this->assign('stats_url', url($this->actionPrefix . '_stats'));
        $this->assign('reload_url', url($this->actionPrefix . '_reload'));
        
        return $this->display('index');
    }
    
    
    /**
     * 柔性重启
     */
    public function reload()
    {
        return $this->submit('request', function() {
            if ($this->isSwoole) {
                $this->getManager()->getServer()->reload();
                
                return '柔性重启成功';
            } else {
                $result = $this->http();
                
                $this->log('柔性重启任务');
                
                return $result['info'];
            }
        });
    }
    
    
    /**
     * 获取状态信息
     */
    public function stats()
    {
        return $this->submit('request', function() {
            if ($this->isSwoole) {
                $stats = $this->getManager()->getServer()->stats();
                
                $stats['format_start_time'] = Transform::date($stats['start_time']);
                $stats['continue_second']   = time() - $stats['start_time'];
                $stats['format_continue']   = $this->getTimeHour($stats['continue_second']);
                
                return $this->success('', '', $stats);
            } else {
                $result = $this->http();
                
                return $this->success('', '', $result['data']);
            }
        });
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
    
    
    /**
     * 获取manager
     * @return Manager
     */
    protected function getManager() : Manager
    {
        return $this->app->make(Manager::class);
    }
    
    
    /**
     * 执行HTTP通讯
     * @param string $url
     * @param bool   $isPost
     * @return array
     * @throws AppException
     */
    protected function http($url = '', $isPost = true)
    {
        $url = (string) url($url, [
            'swoole' => 1,
            '_ajax'  => 1
        ]);
        $url = 'http://' . $this->getConfig('server.host') . ':' . $this->getConfig('server.port') . $url;
        
        try {
            $http = Http::init();
            $http->setTimeout(5);
            $http->setCookies($this->request->cookie());
            
            if ($isPost) {
                $result = Http::post($url, $_POST, $http);
            } else {
                $result = Http::get($url, $_GET, $http);
            }
            
            return json_decode($result, true);
        } catch (\Exception $e) {
            throw new AppException("{$url}无法链接守护程序<br />{$e->getMessage()}");
        }
    }
    
    
    /**
     * 获取面板配置
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    protected function getAdminConfig($name, $default = null)
    {
        return $this->getSwooleConfig('admin.manager.' . $name, $default);
    }
    
    
    /**
     * 获取swoole配置
     * @param string $name
     * @param mixed  $default
     * @return array|mixed|null
     */
    protected function getConfig($name, $default = null)
    {
        return $this->app->config->get('swoole.' . $name, $default);
    }
    
    
    protected function display($template = '', $charset = 'utf-8', $contentType = '', $content = '')
    {
        if (!is_file($template)) {
            $dir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR;
            if ($template) {
                $template = $dir . $template . '.html';
            } else {
                $template = $dir . ACTION_NAME . '.html';
            }
        }
        
        return parent::display($template, $charset, $contentType, $content);
    }
}