<?php

namespace Tofex\Task\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Zend_Db_Exception;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class InstallSchema
    implements InstallSchemaInterface
{
    /**
     * @param SchemaSetupInterface   $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     * @throws Zend_Db_Exception
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $connection = $setup->getConnection();

        $runTableName = $setup->getTable('task_run');

        if ($connection->isTableExists($runTableName)) {
            $connection->dropTable($runTableName);
        }

        if ($connection->isTableExists('job_run')) {
            $connection->renameTable('job_run', $runTableName);
        }

        $runTable = $connection->newTable($runTableName);

        $runTable->addColumn('run_id', Table::TYPE_INTEGER, 10, [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary'  => true
        ], 'The identifier of the run');
        $runTable->addColumn('store_code', Table::TYPE_TEXT, 255, ['nullable' => false]);
        $runTable->addColumn('task_name', Table::TYPE_TEXT, 255, ['nullable' => false]);
        $runTable->addColumn('task_id', Table::TYPE_TEXT, 255, ['nullable' => false]);
        $runTable->addColumn('process_id', Table::TYPE_INTEGER, 10,
            ['unsigned' => true, 'nullable' => false, 'default' => 0]);
        $runTable->addColumn('test', Table::TYPE_SMALLINT, 1,
            ['unsigned' => true, 'nullable' => false, 'default' => 0]);
        $runTable->addColumn('success', Table::TYPE_SMALLINT, 1,
            ['unsigned' => true, 'nullable' => false, 'default' => 1]);
        $runTable->addColumn('empty_run', Table::TYPE_SMALLINT, 1,
            ['unsigned' => true, 'nullable' => false, 'default' => 0]);
        $runTable->addColumn('max_memory_usage', Table::TYPE_INTEGER, 10, ['nullable' => false]);
        $runTable->addColumn('start_at', Table::TYPE_DATETIME, null, ['nullable' => false]);
        $runTable->addColumn('finish_at', Table::TYPE_DATETIME, null, ['nullable' => true]);

        $connection->createTable($runTable);

        $setup->endSetup();
    }
}
