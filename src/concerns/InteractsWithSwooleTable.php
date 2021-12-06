<?php

namespace BusyPHP\swoole\concerns;

use BusyPHP\App;
use Swoole\Table as SwooleTable;
use think\Container;
use BusyPHP\swoole\Table;

/**
 * Swoole Table类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2021 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2021/12/6 下午9:29 InteractsWithSwooleTable.php $
 * @property Container $container
 * @property App       $app
 */
trait InteractsWithSwooleTable
{
    /**
     * @var Table
     */
    protected $currentTable;
    
    
    /**
     * Register customized swoole tables.
     */
    protected function prepareTables()
    {
        $this->currentTable = new Table();
        $this->registerTables();
        $this->onEvent('workerStart', function() {
            $this->app->instance(Table::class, $this->currentTable);
            foreach ($this->currentTable->getAll() as $name => $table) {
                $this->app->instance("swoole.table.{$name}", $table);
            }
        });
    }
    
    
    /**
     * Register user-defined swoole tables.
     */
    protected function registerTables()
    {
        $tables = $this->container->make('config')->get('swoole.tables', []);
        
        foreach ($tables as $key => $value) {
            $table   = new SwooleTable($value['size']);
            $columns = $value['columns'] ?? [];
            foreach ($columns as $column) {
                if (isset($column['size'])) {
                    $table->column($column['name'], $column['type'], $column['size']);
                } else {
                    $table->column($column['name'], $column['type']);
                }
            }
            $table->create();
            
            $this->currentTable->add($key, $table);
        }
    }
}
