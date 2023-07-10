<?php

namespace yii1tech\ar\softdelete\test;

use yii1tech\ar\softdelete\SoftDeleteBehavior;
use yii1tech\ar\softdelete\test\data\Category;
use yii1tech\ar\softdelete\test\data\Item;

class SoftDeleteBehaviorFindTest extends TestCase
{
    public function testFindCriteria(): void
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

    /**
     * Data provider for {@see testFilterDeleted()}
     *
     * @return array test data.
     */
    public static function dataProviderFilterDeleted(): array
    {
        return [
            ['', 2],
            [null, 2],
            ['1', 1],
            [true, 1],
            ['0', 3],
            [false, 3],
            ['all', 3],
        ];
    }

    /**
     * @dataProvider dataProviderFilterDeleted
     *
     * @param mixed $filterDeleted
     * @param int $expectedCount
     */
    public function testFilterDeleted($filterDeleted, $expectedCount): void
    {
        Category::model()
            ->findByPk(2)
            ->softDelete();

        $this->assertCount($expectedCount, Category::model()->filterDeleted($filterDeleted)->findAll());
    }

    public function testAutoApplyNotDeletedCondition(): void
    {
        Category::model()
            ->findByPk(2)
            ->softDelete();

        /** @var Category|SoftDeleteBehavior $query */
        $query = Category::model();
        $query->autoApplyNotDeletedCondition = true;

        $this->assertCount(2, $query->findAll());

        /** @var Category|SoftDeleteBehavior $query */
        $query = Category::model();
        $query->autoApplyNotDeletedCondition = true;
        $this->assertCount(1, $query->deleted()->findAll());

        /** @var Category|SoftDeleteBehavior $query */
        $query = Category::model();
        $query->autoApplyNotDeletedCondition = true;
        $this->assertCount(3, $query->filterDeleted('all')->findAll());
    }
}