Swoole守护程序
===============

> 基于 `think-swoole` 开发<br />
> 支持异步数据处理，如批量发送邮件、短信等。可用于创建Http服务，Websocket服务、Tcp服务、Rpc服务以脱离Apache、Nginx等独立运行，独立运行环境下支持 `Swoole` 协程开发

## 安装方式

```shell script
composer require busyphp/swoole
```

> 安装完成后可以通过后台管理 > 开发模式 > 插件管理进行 `安装/卸载/管理`

## 服务命令

适用于 `http`，`tcp`，`websocket`, `rpc` 等服务<br />
`cd` 到到项目根目录下执行

### 启动命令

```shell script
php think swoole
```

### 停止命令
```shell script
php think swoole stop
```

### 重启命令
```shell script
php think swoole restart
```

### 在`www`用户下运行

```shell script
su -c "php think swoole start|stop|restart" -s /bin/sh www
```

## 队列服务

### 队列说明

参考：[https://github.com/busyphp/queue#readme](https://github.com/busyphp/queue#readme)

#### 配置 `config/swoole.php`

```php
<?php
return [
    // 此处省略...

    // 队列配置
    'queue'      => [
        'enable'  => false,
        'workers' => [
            // 队列名称 => [队列配置]
            'default' => [
                // 设置使用哪个队列连接器，默认依据 `config/queue.php` 中的 `default` 确定
                'connection' => '',
                
                // 启动几个worker并行执行
                'number'     => 1,
                
                // 如果本次任务执行抛出异常且任务未被删除时，设置其下次执行前延迟多少秒
                'delay'      => 0,
                
                // 如果队列中无任务，则多长时间后重新检查
                'sleep'      => 3,
                
                // 如果任务已经超过尝试次数上限，0为不限，则触发当前任务类下的failed()方法
                'tries'      => 0,
                
                // 进程的允许执行的最长时间，以秒为单位
                'timeout'    => 60,
            ],
            
            // 更多队列配置
        ],
    ],

    // 此处省略...
];
```

## 定时任务服务

### 定时任务配置

#### 配置 `config/swoole.php`

```php
return [
    // 此处省略...

    // 定时任务配置
    'timer'      => [
        'enable'  => true,
        'workers' => [
            // 计时器类名，必须实现 \BusyPHP\swoole\contract\timer\TimerInterface 接口
            TimerTask::class
        ],
    ],

    // 此处省略...
];
```

#### 创建计时器类

```php
<?php
class TimerTask implements \BusyPHP\swoole\contract\timer\TimerInterface {
    
}
```