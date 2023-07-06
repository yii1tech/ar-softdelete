<?php

namespace yii1tech\ar\softdelete\test;

use yii1tech\ar\softdelete\SoftDeleteBehavior;
use yii1tech\ar\softdelete\test\data\Category;
use yii1tech\ar\softdelete\test\data\Item;

class SoftDeleteBehaviorTest extends TestCase
{
    public function testSoftDelete()
    {
        /* @var $item Item|SoftDeleteBehavior */
        $item = Item::model()->findByPk(2);

        $result = $item->softDelete();

        $this->assertTrue($result);
        $this->assertEquals(true, $item->is_deleted);

        $item->refresh();
        $this->assertEquals(true, $item->is_deleted);
    }

    public function testReplaceDelete()
    {
        /* @var $item Item|SoftDeleteBehavior */

        $item = Item::model()->findByPk(2);
        $item->replaceRegularDelete = true;
        $item->delete();

        $this->assertEquals(true, $item->is_deleted);

        $item->refresh();
        $this->assertEquals(true, $item->is_deleted);

        $this->assertEquals(4, Item::model()->count());
    }

    /**
     * @depends testSoftDelete
     */
    public function testAllowDelete()
    {
        /* @var $item Item|SoftDeleteBehavior */

        $item = Item::model()->findByPk(1);
        $item->replaceRegularDelete = true;
        $item->name = 'allow-delete';
        $item->softDelete();

        $this->assertEquals(3, Item::model()->count());
    }

    /**
     * @depends testSoftDelete
     */
    public function testRestore()
    {
        /* @var $item Item|SoftDeleteBehavior */
        $item = Item::model()->findByPk(2);

        $item->softDelete();
        $result = $item->restore();

        $this->assertTrue($result);
        $this->assertEquals(false, $item->is_deleted);
        $item->refresh();
        $this->assertEquals(false, $item->is_deleted);
    }

    /**
     * @depends testRestore
     */
    public function testCallback()
    {
        /* @var $item Item|SoftDeleteBehavior */

        $nowTimestamp = time();

        $item = Item::model()->findByPk(2);

        $item->softDeleteAttributeValues = [
            'deleted_at' => function() {
                return time();
            }
        ];
        $item->softDelete();

        $this->assertTrue($item->deleted_at >= $nowTimestamp);

        $item = Item::model()->findByPk(2);
        $item->restoreAttributeValues = [
            'deleted_at' => function() {
                return null;
            }
        ];
        $item->restore();

        $this->assertNull($item->deleted_at);
    }

    /**
     * @depends testSoftDelete
     */
    public function testSafeDelete()
    {
        /** @var $item Item|SoftDeleteBehavior */

        // actual delete
        $item = Item::model()->findByPk(3);
        $result = $item->safeDelete();

        $this->assertEquals(true, $result);
        $this->assertNull(Item::model()->findByPk(3));

        // fallback
        $item = Item::model()->findByPk(4);
        $item->throwOnDeleteException = true;
        $result = $item->safeDelete();

        $this->assertEquals(true, $result);
        $item = Item::model()->findByPk(4);
        $this->assertNotNull($item);
        $this->assertEquals(true, $item->is_deleted);

        // custom exception class
        $item = Item::model()->findByPk(4);
        $item->throwOnDeleteException = true;
        $item->onDeleteExceptionClass = \LogicException::class;
        $item->deleteFallbackException = $item->onDeleteExceptionClass;

        $item->safeDelete();
        $this->assertNotNull(Item::model()->findByPk(4));
        $this->assertEquals(true, $item->is_deleted);

        $item->onDeleteExceptionClass = \RuntimeException::class;

        try {
            $item->is_deleted = false;
            $item->safeDelete();
            $this->assertTrue(false, 'No exception thrown');
        } catch (\Exception $exception) {
            $this->assertEquals(\RuntimeException::class, get_class($exception));
            $this->assertEquals(false, $item->is_deleted);
        }
    }

    /**
     * @depends testRestore
     */
    public function testUseRestoreAttributeValuesAsDefaults()
    {
        $category = new Category();
        $category->name = 'apply restore attribute';
        $category->save(false);
        $this->assertSame(false, $category->is_deleted);

        $category = new Category();
        $category->name = 'prevent restore attribute application';
        $category->is_deleted = true;
        $category->save(false);
        $this->assertSame(true, $category->is_deleted);
    }
}