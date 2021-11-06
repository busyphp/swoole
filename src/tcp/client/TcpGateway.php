<?php

namespace BusyPHP\swoole\tcp\client;

use BusyPHP\swoole\contract\BaseGateway;

/**
 * TCP网关
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/5 上午11:22 TcpGateway.php $
 */
class TcpGateway extends BaseGateway
{
    /**
     * 给客户端发送送数据
     * @param int    $clientId 客户端ID
     * @param string $data 数据
     */
    public function send(int $clientId, string $data)
    {
        if ($this->canRun()) {
            $this->server()->send($clientId, $data);
        } else {
            $this->gateway(__FUNCTION__, func_get_args());
        }
    }
}