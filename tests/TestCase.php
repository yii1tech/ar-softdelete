<?php

namespace yii1tech\ar\softdelete\test;

use CConsoleApplication;
use CMap;
use Yii;

class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->mockApplication();

        $this->setupTestDbData();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        $this->destroyApplication();

        parent::tearDown();
    }

    /**
     * Populates Yii::app() with a new application
     * The application will be destroyed on tearDown() automatically.
     * @param array $config The application configuration, if needed
     * @param string $appClass name of the application class to create
     */
    protected function mockApplication($config = [], $appClass = CConsoleApplication::class)
    {
        Yii::setApplication(null);

        new $appClass(CMap::mergeArray([
            'id' => 'testapp',
            'basePath' => __DIR__,
            'components' => [
                'db' => [
                    'class' => \CDbConnection::class,
                    'connectionString' => 'sqlite::memory:',
                ],
                'cache' => [
                    'class' => \CDummyCache::class,
                ],
            ],
        ], $config));
    }

    /**
     * Destroys Yii application by setting it to null.
     */
    protected function destroyApplication()
    {
        Yii::setApplication(null);
    }

    /**
     * Setup tables for test ActiveRecord
     */
    protected function setupTestDbData()
    {
        $db = Yii::app()->getDb();

        // Structure :

        $db->createCommand()
            ->createTable('category', [
                'id' => 'pk',
                'name' => 'string',
                'is_deleted' => 'boolean',
            ]);

        $db->createCommand()
            ->createTable('item', [
                'id' => 'pk',
                'category_id' => 'integer',
                'name' => 'string',
                'is_deleted' => 'boolean DEFAULT 0',
                'deleted_at' => 'integer',
            ]);

        // Data :

        $builder = $db->getCommandBuilder();

        $table = 'category';
        $categoryIds = [];

        $builder->createInsertCommand($table, ['name' => 'category1', 'is_deleted' => false])->execute();
        $categoryIds[] = $builder->getLastInsertID($table);
        $builder->createInsertCommand($table, ['name' => 'category2', 'is_deleted' => false])->execute();
        $categoryIds[] = $builder->getLastInsertID($table);
        $builder->createInsertCommand($table, ['name' => 'category3', 'is_deleted' => false])->execute();
        $categoryIds[] = $builder->getLastInsertID($table);

        $builder->createMultipleInsertCommand('item', [
            [
                'name' => 'item1',
                'category_id' => $categoryIds[0],
            ],
            [
                'name' => 'item2',
                'category_id' => $categoryIds[1],
            ],
            [
                'name' => 'item3',
                'category_id' => $categoryIds[0],
            ],
            [
                'name' => 'item4',
                'category_id' => $categoryIds[1],
            ],
        ])->execute();
    }
}