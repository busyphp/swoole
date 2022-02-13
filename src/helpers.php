<?php

if (!function_exists('swoole_cpu_num')) {
    function swoole_cpu_num() : int
    {
        return 1;
    }
}

if (!defined('SWOOLE_SOCK_TCP')) {
    define('SWOOLE_SOCK_TCP', 1);
}

if (!defined('SWOOLE_PROCESS')) {
    define('SWOOLE_PROCESS', 3);
}

if (!defined('SWOOLE_HOOK_ALL')) {
    define('SWOOLE_HOOK_ALL', 1879048191);
}

if (!defined('SWOOLE_WORKER_BUSY')) {
    define('SWOOLE_WORKER_BUSY', 1);
}

if (!defined('SWOOLE_WORKER_IDLE')) {
    define('SWOOLE_WORKER_IDLE', 2);
}

if (!defined('SWOOLE_WORKER_EXIT')) {
    define('SWOOLE_WORKER_EXIT', 3);
}

if (!defined('SWOOLE_HTTP_CLIENT_ESTATUS_SEND_FAILED')) {
    define('SWOOLE_HTTP_CLIENT_ESTATUS_SEND_FAILED', -4);
}