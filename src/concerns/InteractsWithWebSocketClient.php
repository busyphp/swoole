<?php

namespace BusyPHP\swoole\concerns;

use BusyPHP\exception\VerifyException;
use BusyPHP\helper\ArrayHelper;
use BusyPHP\swoole\websocket\client\WebSocketClient;
use Swlib\SaberGM;
use Swoole\Process;
use Swoole\Runtime;
use Swoole\Server;
use Swoole\WebSocket\CloseFrame;
use Swoole\WebSocket\Frame;
use Throwable;

/**
 * WebSocket客户端
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2022 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2022/2/12 10:47 PM InteractsWithWebSocketClient.php $
 * @method Server getServer()
 * @method void runInSandbox(\Closure $callable)
 */
trait InteractsWithWebSocketClient
{
    /**
     * 准备Websocket客户端
     */
    protected function prepareWebSocketClient()
    {
        $clients = $this->getSwooleConfig('websocket.client', []);
        if (!$clients) {
            return;
        }
        
        $this->waitCoordinator('workerStart');
        foreach ($clients as $key => $client) {
            $url     = ArrayHelper::get($client, 'url', '');
            $handler = ArrayHelper::get($client, 'handler', '');
            $name    = ArrayHelper::get($clients, 'name', '') ?: $key;
            if (!$url || !$handler || !is_subclass_of($handler, WebSocketClient::class)) {
                continue;
            }
            
            $this->getServer()->addProcess(new Process(function(Process $process) use ($url, $handler, $name) {
                Runtime::enableCoroutine();
                
                $this->clearCache();
                $this->prepareApplication();
                $this->setProcessName("websocket client {$name} progress");
                
                try {
                    $this->createWebsocketClient($process, $url, $handler);
                } catch (Throwable $e) {
                    $this->logServerError($e);
                }
            }, false, 0, true));
        }
    }
    
    
    /**
     * 创建客户端
     * @param Process $process
     * @param string  $url
     * @param string  $handler
     */
    protected function createWebsocketClient(Process $process, string $url, string $handler)
    {
        /** @var WebSocketClient $client */
        $client = $this->container->make($handler, [$process, $url]);
        $this->runWithBarrier([$this, 'runInSandbox'], function() use ($client) {
            $client->onBefore();
        });
        
        try {
            $ws = SaberGM::websocket($url);
            $client->setWebsocket($ws);
            $this->runWithBarrier([$this, 'runInSandbox'], function() use ($client) {
                $client->onOpen();
            });
            
            while (true) {
                $result = $ws->client->recv();
                
                // 连接被关闭时返回 false 并且 errCode=0
                if ($result === false && $ws->client->errCode === 0) {
                    throw new VerifyException($ws->client->errMsg, '@@websocket_closed@@', $ws->client->statusCode);
                }
                
                // 连接被服务器关闭
                if ($result instanceof CloseFrame) {
                    $message = serialize($result);
                    throw new VerifyException($message, '@@websocket_close_frame@@');
                }
                
                // 收到消息
                if ($result instanceof Frame) {
                    $this->runWithBarrier([$this, 'runInSandbox'], function() use ($client, $result) {
                        $client->onMessage($result);
                    });
                }
            }
        } catch (Throwable $e) {
            // 连接被关闭
            if ($e instanceof VerifyException && $e->getField() === '@@websocket_close_frame@@') {
                $this->runWithBarrier([$this, 'runInSandbox'], function() use ($client, $e) {
                    /** @var CloseFrame $closeFrame */
                    $closeFrame = unserialize($e->getMessage());
                    if (!$closeFrame->reason) {
                        switch ($closeFrame->code) {
                            case WEBSOCKET_CLOSE_NORMAL:
                                $closeFrame->reason = 'close normal';
                            break;
                            case WEBSOCKET_CLOSE_GOING_AWAY:
                                $closeFrame->reason = 'close going away';
                            break;
                            case WEBSOCKET_CLOSE_PROTOCOL_ERROR:
                                $closeFrame->reason = 'close protocol error';
                            break;
                            case WEBSOCKET_CLOSE_DATA_ERROR:
                                $closeFrame->reason = 'close data error';
                            break;
                            case WEBSOCKET_CLOSE_STATUS_ERROR:
                                $closeFrame->reason = 'close status error';
                            break;
                            case WEBSOCKET_CLOSE_ABNORMAL:
                                $closeFrame->reason = 'close_abnormal';
                            break;
                            case WEBSOCKET_CLOSE_MESSAGE_ERROR:
                                $closeFrame->reason = 'close message error';
                            break;
                            case WEBSOCKET_CLOSE_POLICY_ERROR:
                                $closeFrame->reason = 'close policy error';
                            break;
                            case WEBSOCKET_CLOSE_MESSAGE_TOO_BIG:
                                $closeFrame->reason = 'close message too big';
                            break;
                            case WEBSOCKET_CLOSE_EXTENSION_MISSING:
                                $closeFrame->reason = 'close extension missing';
                            break;
                            case WEBSOCKET_CLOSE_SERVER_ERROR:
                                $closeFrame->reason = 'close server error';
                            break;
                            case WEBSOCKET_CLOSE_TLS:
                                $closeFrame->reason = 'close tls';
                            break;
                        }
                    }
                    $client->onClose($closeFrame);
                });
                
                return;
            }
            
            //
            // 非正常关闭
            elseif ($e instanceof VerifyException && $e->getField() === '@@websocket_closed@@') {
                $this->runWithBarrier([$this, 'runInSandbox'], function() use ($client, $e) {
                    $message = $e->getMessage();
                    if (!$message) {
                        switch ($e->getCode()) {
                            case SWOOLE_HTTP_CLIENT_ESTATUS_CONNECT_FAILED:
                                $message = '连接超时，服务器未监听端口或网络丢失，可以读取 $errCode 获取具体的网络错误码';
                            break;
                            case SWOOLE_HTTP_CLIENT_ESTATUS_REQUEST_TIMEOUT:
                                $message = '请求超时，服务器未在规定的 timeout 时间内返回 response';
                            break;
                            case SWOOLE_HTTP_CLIENT_ESTATUS_SERVER_RESET:
                                $message = '客户端请求发出后，服务器强制切断连接';
                            break;
                            case SWOOLE_HTTP_CLIENT_ESTATUS_SEND_FAILED:
                                $message = '客户端发送失败';
                            break;
                        }
                    }
                    
                    $closeFrame         = new CloseFrame();
                    $closeFrame->opcode = WEBSOCKET_OPCODE_CLOSE;
                    $closeFrame->code   = WEBSOCKET_CLOSE_ABNORMAL;
                    $closeFrame->reason = $message ?: 'close abnormal';
                    $client->onClose($closeFrame);
                });
                
                return;
            }
            
            // 连接错误
            $this->runWithBarrier([$this, 'runInSandbox'], function() use ($e, $client) {
                $client->onError($e);
            });
        }
    }
}