<?php

namespace BusyPHP\swoole\concerns;

use ReflectionException;
use ReflectionObject;

/**
 * 通过类反射修改
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/11/4 下午12:03 ModifyProperty.php $
 */
trait ModifyProperty
{
    /**
     * 修改类保护属性
     * @param object $object 类
     * @param mixed  $value 值
     * @param string $property 属性
     * @throws ReflectionException
     */
    protected function modifyProperty($object, $value, $property = 'app')
    {
        $reflectObject = new ReflectionObject($object);
        if ($reflectObject->hasProperty($property)) {
            $reflectProperty = $reflectObject->getProperty($property);
            $reflectProperty->setAccessible(true);
            $reflectProperty->setValue($object, $value);
        }
    }
}
