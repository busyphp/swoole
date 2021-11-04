<?php

use BusyPHP\swoole\websocket\socketio\Handler;

return [
    // HTTP服务配置
    'server'     => [
        // 监听地址
        'host'      => env('SWOOLE_HOST', '127.0.0.1'),
        
        // 监听端口
        'port'      => env('SWOOLE_PORT', 80),
        
        // 运行模式 默认为SWOOLE_PROCESS
        'mode'      => SWOOLE_PROCESS,
        
        // sock type 默认为SWOOLE_SOCK_TCP
        'sock_type' => SWOOLE_SOCK_TCP,
        
        // swoole配置
        // 请参考: https://wiki.swoole.com/#/server/setting
        'options'   => [
            // 设置 pid 文件地址
            'pid_file'              => runtime_path() . 'swoole.pid',
            
            // 指定 Swoole 错误日志文件
            'log_file'              => runtime_path() . 'swoole.log',
            
            // 是否开启守护进程模式
            'daemonize'             => false,
            
            // 设置启动的 Reactor 线程数
            // https://wiki.swoole.com/#/server/setting?id=reactor_num
            // 通过此参数来调节主进程内事件处理线程的数量，以充分利用多核。默认会启用 CPU 核数相同的数量。
            // Reactor 线程是可以利用多核，如：机器有 128 核，那么底层会启动 128 线程。
            // 每个线程能都会维持一个 EventLoop。线程之间是无锁的，指令可以被 128 核 CPU 并行执行。
            // 考虑到操作系统调度存在一定程度的性能损失，可以设置为 CPU 核数 * 2，以便最大化利用 CPU 的每一个核
            'reactor_num'           => swoole_cpu_num(),
            
            
            // 设置启动的 Worker 进程数
            // https://wiki.swoole.com/#/server/setting?id=reactor_num
            // 如 1 个请求耗时 100ms，要提供 1000QPS 的处理能力，那必须配置 100 个进程或更多。
            // 但开的进程越多，占用的内存就会大大增加，而且进程间切换的开销就会越来越大。所以这里适当即可。不要配置过大。
            // 1. 如果业务代码是全异步 IO 的，这里设置为 CPU 核数的 1-4 倍最合理
            // 2. 最大不得超过 swoole_cpu_num() * 1000
            // 3. 假设每个进程占用 40M 内存，100 个进程就需要占用 4G 内存
            'worker_num'            => swoole_cpu_num(),
            
            // 配置 Task 进程的数量
            // https://wiki.swoole.com/#/server/setting?id=task_worker_num
            // 计算方法:
            // - 单个 task 的处理耗时，如 100ms，那一个进程 1 秒就可以处理 1/0.1=10 个 task
            // - task 投递的速度，如每秒产生 2000 个 task
            // - 2000/10=200，需要设置 task_worker_num => 200，启用 200 个 Task 进程
            'task_worker_num'       => swoole_cpu_num(),
            
            
            // 开启静态文件请求处理功能，需配合 document_root 使用
            // https://wiki.swoole.com/#/http_server?id=enable_static_handler
            'enable_static_handler' => true,
            
            // 配置静态文件根目录，与 enable_static_handler 配合使用
            // https://wiki.swoole.com/#/http_server?id=document_root
            // 此功能较为简易，请勿在公网环境直接使用
            'document_root'         => root_path('public'),
            
            // 设置最大数据包尺寸，单位为字节
            // https://wiki.swoole.com/#/server/setting?id=package_max_length
            'package_max_length'    => 20 * 1024 * 1024,
            
            // 配置发送输出缓存区内存尺寸
            // https://wiki.swoole.com/#/server/setting?id=buffer_output_size
            'buffer_output_size'    => 10 * 1024 * 1024,
            
            // 配置客户端连接的缓存区长度
            // https://wiki.swoole.com/#/server/setting?id=socket_buffer_size
            'socket_buffer_size'    => 128 * 1024 * 1024,
        ],
    ],
    
    // WebSocket配置
    'websocket'  => [
        'enable'        => false,
        'handler'       => Handler::class,
        'ping_interval' => 25000,
        'ping_timeout'  => 60000,
        'room'          => [
            'type'  => 'table',
            'table' => [
                'room_rows'   => 4096,
                'room_size'   => 2048,
                'client_rows' => 8192,
                'client_size' => 2048,
            ],
            'redis' => [
                'host'          => '127.0.0.1',
                'port'          => 6379,
                'max_active'    => 3,
                'max_wait_time' => 5,
            ],
        ],
        'listen'        => [],
        'subscribe'     => [],
    ],
    
    // PRC服务器
    'rpc'        => [
        'server' => [
            'enable'   => false,
            'port'     => 9000,
            'services' => [],
        ],
        
        'client' => [],
    ],
    
    // 热更新
    'hot_update' => [
        'enable'  => env('APP_DEBUG', false),
        'name'    => ['*.php'],
        'include' => [
            app()->getRootPath() . 'app',
            app()->getRootPath() . 'core',
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
    
    // 队列
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
    
    'coroutine' => [
        'enable' => true,
        'flags'  => SWOOLE_HOOK_ALL,
    ],
    
    'tables'    => [],
    
    // 每个worker里需要预加载以共用的实例
    'concretes' => [],
    
    // 重置器
    'resetters' => [],
    
    // 每次请求前需要清空的实例
    'instances' => [],
    
    // 每次请求前需要重新执行的服务
    'services'  => [],
];
