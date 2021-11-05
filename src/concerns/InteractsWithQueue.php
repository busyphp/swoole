<?php

namespace BusyPHP\swoole\concerns;

use BusyPHP\swoole\queue\Manager;

/**
 * 准备队列
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/5 上午10:10 InteractsWithQueue.php $
 */
trait InteractsWithQueue
{
    protected function prepareQueue()
    {
        if ($this->getConfig('queue.enable', false)) {
            /** @var Manager $queueManager */
            $queueManager = $this->container->make(Manager::class);
            
            $queueManager->attachToServer($this->getServer());
        }
    }
}
