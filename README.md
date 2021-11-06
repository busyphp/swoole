Swoole守护程序
===============

> 基于 `think-swoole` 开发<br />
> 支持异步数据处理，如批量发送邮件、短信等。可用于创建`Http`服务器，`Websocket`服务器、`Rpc`服务器等脱离`Apache`、`Nginx`等服务器独立运行，独立运行环境下支持`Swoole`携程开发

## 安装方式

```shell script
composer require busyphp/swoole
```

> 安装完成后可以通过后台管理 > 开发模式 > 插件管理进行管理

## 服务命令

适用于 `http`，`tcp`，`websocket`等服务<br />
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

单进程服务，不会出现数据争抢，适用于小批量数据队列处理

### 队列配置

#### 配置 `config/queue.php`
```php
<?php
return [
    // 队列驱动请配置database或redis
    'default'     => 'database', 

    'connections' => [
        // 同步执行驱动，无实际意义，测试用
        'sync'     => [
            'type' => 'sync',
        ],
    
        // 数据库驱动
        'database' => [
            // 任务重新入队后延迟执行的秒数，不设置默认为60秒
            'retry_after' => 60,

            // 更多选项请查看源文件
        ],
        
        // Redis驱动
        'redis'    => [
            // 任务重新入队后延迟执行的秒数，不设置默认为60秒
            'retry_after' => 60, 

            // 更多选项请查看源文件
        ],
    ],

    // 此处省略...
];

```

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
                // 启动后延迟多少秒执行
                'delay'   => 0,
                
                // 无任务休眠多少秒后继续查任务
                'sleep'   => 3,
                
                // 任务失败最大重试次数
                'tries'   => 3,
                
                // 任务最大执行时长秒数
                'timeout' => 60,
            ],
            
            // 更多队列配置
        ],
    ],

    // 此处省略...
];
```

#### 创建任务类

> 单任务类推荐集成 `\BusyPHP\swoole\contract\JobInterface` 接口类，以便编辑器辅助提示 <br />
> 如果有多个小任务，就写多个方法，下面发布任务的时候会有区别

##### 下面写两个例子

```php
<?php
namespace app\job;

use BusyPHP\swoole\contract\JobInterface;
use think\queue\Job;

class Job1 implements JobInterface
{
    /**
     * 执行任务
     * @param Job   $job 任务对象
     * @param mixed $data 任务数据
     */
    public function fire(Job $job, $data) : void
    {
        // 这里执行具体的任务....
    
        // 通过这个方法可以检查这个任务已经重试了几次了
        if ($job->attempts() > 3) {
            // ...
        }
    
        //如果任务执行成功后 记得删除任务，不然这个任务会重复执行，直到达到最大重试次数后失败后，执行failed方法
        $job->delete();
    
        // 也可以重新发布这个任务
        $job->release($delay); //$delay为延迟时间(秒)
    }
    
    
    /**
     * 执行任务达到最大重试次数后失败
     * @param mixed $data 发布任务时自定义的数据
     */
    public function failed($data) : void
    {
    }
}
```

```php
<?php
namespace app\job;

use think\queue\Job;

class Job1 
{
    /**
     * 执行任务
     * @param Job   $job 任务对象
     * @param mixed $data 任务数据
     */
    public function task1(Job $job, $data) : void
    {
        // 这里执行 task1 具体的任务....
    }
    
    /**
     * 执行任务
     * @param Job   $job 任务对象
     * @param mixed $data 任务数据
     */
    public function task2(Job $job, $data) : void
    {
        // 这里执行 task2 具体的任务....
    }
    
    /**
     * 执行任务
     * @param Job   $job 任务对象
     * @param mixed $data 任务数据
     */
    public function task3(Job $job, $data) : void
    {
        // 这里执行 task3 具体的任务....
    }
        
    /**
     * 执行任务达到最大重试次数后失败
     * @param mixed $data 发布任务时自定义的数据
     */
    public function failed($data) : void
    {
    }
}
```

#### 发布任务

##### 立即执行

```php
<?php
/**
 * @params string $job 任务名
 * @params mixed $data 你要传到任务里的参数
 * @params string|null $queue 队列名，指定这个任务是在哪个队列上执行，同下面监控队列的时候指定的队列名,可不填，默认为 default
 */
\think\facade\Queue::push($job, $data = '', $queue = null);

// $job 多个小任务写法
// $job 是任务名
// 单模块的，且命名空间是app\job的，比如上面的例子一,写Job1类名即可
// 多模块的，且命名空间是app\module\job的，写model/Job1即可
// 其他的需要些完整的类名，比如上面的例子二，需要写完整的类名app\lib\job\Job2
// 如果一个任务类里有多个小任务的话，如上面的例子二，需要用@+方法名app\lib\job\Job2@task1、app\lib\job\Job2@task2
```