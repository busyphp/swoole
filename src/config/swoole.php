<?php

use BusyPHP\swoole\tcp\handler\TcpHandler;
use BusyPHP\swoole\websocket\socketio\WebsocketHandler;

return [
    // HTTP服务配置
    'server'     => [
        // 是否公开服务
        'public'  => false,
        
        // 监听地址
        'host'    => env('SWOOLE_HOST', '0.0.0.0'),
        
        // 监听端口
        'port'    => env('SWOOLE_PORT', 80),
        
        // Swoole配置
        // 更多配置请参考: https://wiki.swoole.com/#/server/setting
        'options' => [
            // 是否开启守护进程模式
            'daemonize' => false
        ],
    ],
    
    // 网关
    'gateway'    => [
        'server' => [
            // 网关端口
            'port' => 8083,
        ],
        
        // 通信密钥，为了安全建议设置32个字符串
        'secret' => '',
    ],
    
    // WebSocket配置
    'websocket'  => [
        // WebSocket服务端配置
        'server' => [
            'enable'        => false,
            
            // 处理器，必须继承 \BusyPHP\swoole\Websocket 类
            'handler'       => WebsocketHandler::class,
            
            // 客户端关系管理配置
            'room'          => [
                'type'  => 'table',
                'table' => [
                    'room_rows'   => 8192,
                    'room_size'   => 2048,
                    'client_rows' => 4096,
                    'client_size' => 2048,
                ],
                'redis' => [
                    'host'          => '127.0.0.1',
                    'port'          => 6379,
                    'max_active'    => 3,
                    'max_wait_time' => 5,
                ],
            ],
            
            // 事件监听器
            'listen'        => [],
            
            // 事件订阅
            'subscribe'     => [],
            
            // ping包间隔毫秒
            'ping_interval' => 25000,
            
            // 客户端多少时间未发送ping包则关闭连接
            'ping_timeout'  => 60000,
        ],
        
        
        // WebSocket客户端配置
        'client' => [
            // [
            //    'url'     => 'ws://www.domain.com/',
            //    'handler' => '处理类名，必须继承 \BusyPHP\swoole\websocket\client\WebSocketClient 类型',
            // ]
        ]
    ],
    
    // PRC服务器
    'rpc'        => [
        'server' => [
            'enable'   => false,
            'port'     => 8082,
            'services' => [],
        ],
        
        'client' => [],
    ],
    
    // TCP服务器
    'tcp'        => [
        'server'  => [
            'enable' => false,
            'port'   => 8081,
        ],
        
        // 处理器，必须集成 \BusyPHP\swoole\contract\tcp\TcpHandleInterface 接口
        'handler' => TcpHandler::class
    ],
    
    // 热更新
    'hot_update' => [
        'enable'  => env('APP_DEBUG', false),
        'name'    => ['*.php'],
        'include' => [
            app()->getRootPath() . 'app',
            app()->getRootPath() . 'core',
            app()->getRootPath() . 'config',
            app()->getRootPath() . 'extend',
        ],
        'exclude' => [],
    ],
    
    // 连接池
    'pool'       => [
        // 数据库连接池
        'db'    => [
            'enable'        => true,
            'max_active'    => 3,
            'max_wait_time' => 5,
        ],
        
        // 缓存连接池
        'cache' => [
            'enable'        => true,
            'max_active'    => 3,
            'max_wait_time' => 5,
        ],
        
        //自定义连接池
    ],
    
    // 队列任务
    // 不会争抢数据，单进程处理
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
    
    // 定时器
    'timer'      => [
        'enable'  => false,
        'workers' => [
            // 计时器类名，必须实现 \BusyPHP\swoole\contract\timer\TimerInterface 接口
        ]
    ],
    
    // 自定义 Swoole Table
    'tables'     => [],
    
    // 每个worker里需要预加载以共用的实例
    'concretes'  => [],
    
    // 重置器
    'resetters'  => [],
    
    // 每次请求前需要清空的实例
    'instances'  => [],
    
    // 每次请求前需要重新执行的服务
    'services'   => [],
];
