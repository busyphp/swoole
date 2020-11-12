<?php

/**
 * Swoole相关配置
 */
return [
    // 后台配置
    'admin' => [
        // 守护程序管理
        'manager' => [
            // 菜单配置
            // 菜单会直接安装到 System.Index下，
            'menu' => [
                // 页面执行方法名称前缀，请保证不要有重名
                'action_prefix' => 'swoole',
            ]
        ]
    ],
];