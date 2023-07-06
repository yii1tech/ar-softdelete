<?php

namespace yii1tech\ar\softdelete\test;

use yii1tech\ar\softdelete\SoftDeleteBehavior;
use yii1tech\ar\softdelete\test\data\Item;

class SoftDeleteBehaviorFindTest extends TestCase
{
    public function testFindCriteria()
    {
        /** @var Item|SoftDeleteBehavior $query */
        $query = Item::model();

        $allCount = $query->count();

        /** @var Item|SoftDeleteBehavior $item */
        $item = Item::model()->findByPk(4);
        $item->softDelete();

        /** @var Item|SoftDeleteBehavior $query */
        $query = Item::model();
        $deletedCount = $query->deleted()->count();
        $notDeletedCount = $query->notDeleted()->count();

        $this->assertNotEquals($allCount, $deletedCount);
        $this->assertNotEquals($allCount, $notDeletedCount);
    }
}