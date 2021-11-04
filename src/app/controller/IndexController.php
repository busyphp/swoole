<?php

namespace BusyPHP\swoole\app\controller;

use BusyPHP\app\admin\controller\AdminController;
use BusyPHP\helper\HttpHelper;
use BusyPHP\helper\StringHelper;
use BusyPHP\helper\TransHelper;
use BusyPHP\helper\TripleDesHelper;
use BusyPHP\swoole\App;
use BusyPHP\swoole\Manager;
use Exception;
use RuntimeException;
use think\exception\HttpResponseException;
use think\facade\Route;
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
     * 是否运行在Swoole环境下
     * @var bool
     */
    protected $isSwoole;
    
    protected $key = 'S3LvONH1RqCFauhthk6KcamI';
    
    
    protected function initialize($checkLogin = true)
    {
        $this->isSwoole = $this->app instanceof App;
        
        // 解密
        $token = $this->param('token/s', 'trim');
        if ($token) {
            $this->request->setRequestIsAjax();
            try {
                if (!$this->isSwoole) {
                    throw new RuntimeException('当前运行环境不是Swoole环境');
                }
                
                $token = TransHelper::base64decodeUrl($token);
                $token = TripleDesHelper::decrypt($token, $this->key);
                $array = explode(',', $token);
                $path  = $array[0] ?? '';
                if ($path !== $this->request->action()) {
                    throw new RuntimeException('通信失败，通信密钥错误');
                }
                
                $checkLogin = false;
            } catch (Exception $e) {
                throw new HttpResponseException($this->error($e));
            }
        }
        
        parent::initialize($checkLogin);
    }
    
    
    /**
     * 获取运行信息
     * @return Response
     */
    public function info()
    {
        // 运行在Swoole环境下
        if ($this->isSwoole) {
            $stats = $this->getManager()->getServer()->stats();
            
            $stats['format_start_time'] = TransHelper::date($stats['start_time']);
            $stats['continue_second']   = time() - $stats['start_time'];
            $stats['format_continue']   = $this->getTimeHour($stats['continue_second']);
            
            return $this->success($stats);
        } else {
            return $this->gateway(__FUNCTION__);
        }
    }
    
    
    /**
     * 软重载
     * @return Response
     */
    public function reload()
    {
        if ($this->isSwoole) {
            $this->getManager()->getServer()->reload();
            
            return $this->success('刷新成功');
        } else {
            return $this->gateway(__FUNCTION__);
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
    
    
    /**
     * 获取manager
     * @return Manager
     */
    protected function getManager() : Manager
    {
        return $this->app->make(Manager::class);
    }
    
    
    /**
     * 网关
     * @param string $path
     * @param array  $var
     * @return Response
     */
    protected function gateway(string $path, array $var = []) : Response
    {
        // 加密通信密钥
        $str          = StringHelper::random();
        $time         = time();
        $var['token'] = TransHelper::base64encodeUrl(TripleDesHelper::encrypt("{$path},{$str},{$time}", $this->key));
        
        // 构建通信地址
        $host = $this->getConfig('server.host', '127.0.0.1');
        $port = $this->getConfig('server.port', '80');
        $url  = Route::buildUrl($path, $var)->domain("{$host}:{$port}")->build();
        
        // 执行请求
        $http              = HttpHelper::init();
        $result            = HttpHelper::get($url, [], $http);
        $result            = json_decode((string) $result, true) ?: [];
        $result['code']    = $result['code'] ?? 0;
        $result['message'] = $result['message'] ?? '';
        $result['result']  = $result['result'] ?? [];
        $result['url']     = $result['url'] ?? '';
        
        if ($result['code'] !== 1) {
            return $this->error($result['message'], $result['url'], $result['code']);
        }
        
        return $this->success($result['message'], $result['url'], $result['result']);
    }
    
    
    /**
     * 获取配置
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    protected function getConfig($name, $default = null)
    {
        return $this->app->config->get("swoole.{$name}", $default);
    }
}