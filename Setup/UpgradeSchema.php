<?php
namespace Getepay\Getepe\Setup;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\DB\Adapter\AdapterInterface;


class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(
        SchemaSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $setup->startSetup();
        $tableName = $setup->getTable('sales_order');
        if (version_compare($context->getVersion(), '1.0.2', '<')) {

            if (!$setup->getConnection()->tableColumnExists($tableName, 'getepay_webhook_notified_at')) {
                $setup->getConnection()->addColumn(
                    $tableName,
                    'getepay_webhook_notified_at',
                    [
                        'nullable' => true,
                        'type'     => \Magento\Framework\DB\Ddl\Table::TYPE_BIGINT,
                        'comment'  => 'Getepay Webhook Notified Timestamp'
                    ]
                );
            }            
            if (!$setup->getConnection()->tableColumnExists($tableName, 'getepay_update_order_cron_status')) {
                $setup->getConnection()->addColumn(
                    $tableName,
                    'getepay_update_order_cron_status',
                    [
                        'nullable' => false,
                        'default'  => 0,
                        'type'     => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        'comment'  => 'Getepay Update Order Processing Cron # of times executed'
                    ]
                );
            }
            if (!$setup->getConnection()->tableColumnExists($tableName, 'getepay_payment_id')) {
                $setup->getConnection()->addColumn(
                    $tableName,
                    'getepay_payment_id',
                    [
                        'nullable' => true,
                        'type'     => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        'length'   => 255,
                        'comment'  => 'Getepay Payment ID'
                    ]
                );
            }
            if (!$setup->getConnection()->tableColumnExists($tableName, 'getepay_payment_status')) {
                $setup->getConnection()->addColumn(
                    $tableName,
                    'getepay_payment_status',
                    [
                        'nullable' => true,
                        'type'     => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        'length'   => 255,
                        'comment'  => 'Getepay Payment Status'
                    ]
                );
            }   
        }

        $setup->endSetup();
    }
}
