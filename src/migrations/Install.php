<?php

namespace ssplugin\ssmembership\migrations;

use ssplugin\ssmembership\SsMembership;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

class Install extends Migration
{
    /**
     * @var string The database driver to use
     */
    public $driver;

    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
        }

        return true;
    }

   
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    protected function createTables()
    {
        $this->createTable('{{%ssmembership_paymentgateway}}', [
            'id' => $this->primaryKey(),
            'integrationId' => $this->integer()->notNull(),
            'name' => $this->text()->notNull(),
            'handle' => $this->text()->notNull(),
            'livePublicKey' => $this->text()->notNull(),
            'liveSecretKey' => $this->text()->notNull(),
            'testPublicKey' => $this->text()->notNull(),
            'testSecretKey' => $this->text()->notNull(),
            'liveMode' => $this->boolean()->defaultValue(false)->notNull(),
            'webhookUrl' => $this->string(255),
            'webhookSecret' => $this->string(255),
            'webhookUrlTest' => $this->string(255),
            'webhookSecretTest' => $this->string(255),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%ssmembership_plan}}', [
            'id' => $this->primaryKey(),
            'name' => $this->text()->notNull(),
            'prodId' => $this->string(255),
            'priceId' => $this->string(255),
            'handle' => $this->text()->notNull(),
            'amount' => $this->float()->notNull(),
            'currency' => $this->text()->notNull(),
            'interval' => $this->text()->notNull(),
            'enabled' => $this->boolean()->defaultValue(true)->notNull(),
            'isLive' => $this->boolean()->defaultValue(false)->notNull(),
            'userGroupId' => $this->integer()->notNull(),
            'gatewayId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%ssmembership_subscription}}', [
            'id' => $this->primaryKey(),
            'name' => $this->text()->notNull(),
            'userId' => $this->integer()->notNull(),
            'membershipPlanId' => $this->integer()->notNull(),
            'stripeSubId' => $this->text()->notNull(),
            'stripeCustomerId' => $this->text()->notNull(),
            'stripePriceId' => $this->text()->notNull(),
            'payer_email' => $this->text()->notNull(),
            'amount' => $this->float()->notNull(),
            'currency' => $this->text()->notNull(),
            'interval' => $this->text()->notNull(),
            'interval_count' => $this->integer()->defaultValue(1)->notNull(),
            'subStartDate' => $this->dateTime()->notNull(),
            'subEndDate' => $this->dateTime()->notNull(),
            'cancelAt' => $this->dateTime(),
            'canceledAt' => $this->dateTime(),
            'cancelType' => $this->string(50),
            'status' => $this->string(255)->defaultValue('active')->notNull(),
            'isLive' => $this->boolean()->defaultValue(false)->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        $this->createTable('{{%ssmembership_payments}}', [
            'id' => $this->primaryKey(),
            'paymentId' => $this->string(255),
            'subscriptionId' => $this->integer()->notNull(),
            'paymentData' => $this->text(),
            'status' => $this->string(255)->notNull(),
            'amount' => $this->float(),
            'payDate' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);
        
        $this->createTable('{{%ssmembership_log}}', [
            'id' => $this->primaryKey(),
            'referenceId' => $this->integer(),
            'logMessage' => $this->text(),
            'referenceBy' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()
        ]);

        // Foreign Keys
        $this->addForeignKey(null, '{{%ssmembership_plan}}', ['gatewayId'], '{{%ssmembership_paymentgateway}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%ssmembership_plan}}', ['userGroupId'], '{{%usergroups}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%ssmembership_subscription}}', ['userId'], '{{%users}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%ssmembership_subscription}}', ['membershipPlanId'], '{{%ssmembership_plan}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%ssmembership_payments}}', 'subscriptionId', '{{%ssmembership_subscription}}', 'id', 'CASCADE', null);
    }

    /**
     * Removes the tables needed for the Records used by the plugin
     *
     * @return void
     */
    protected function removeTables()
    {
        $this->dropTableIfExists('{{%ssmembership_payments}}');
        $this->dropTableIfExists('{{%ssmembership_subscription}}');
        $this->dropTableIfExists('{{%ssmembership_plan}}');
        $this->dropTableIfExists('{{%ssmembership_paymentgateway}}');
        $this->dropTableIfExists('{{%ssmembership_log}}');
        
    }

    public function dropForeignKeys()
    {
        if ($this->_tableExists('{{%ssmembership_payments}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%ssmembership_subscription}}', $this);
        }

        if ($this->_tableExists('{{%ssmembership_subscription}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%ssmembership_plan}}', $this);
        }

        if ($this->_tableExists('{{%ssmembership_plan}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%ssmembership_plan}}', $this);
        }
        if ($this->_tableExists('{{%ssmembership_paymentgateway}}')) {
            MigrationHelper::dropAllForeignKeysToTable('{{%ssmembership_paymentgateway}}', $this);
        }
    }
    
}
