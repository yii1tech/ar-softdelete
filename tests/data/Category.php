<?php

namespace yii1tech\ar\softdelete\test\data;

use CActiveRecord;
use yii1tech\ar\softdelete\SoftDeleteBehavior;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_deleted
 *
 * @property Item[] $items
 */
class Category extends CActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     * {@inheritdoc}
     */
    public function tableName()
    {
        return 'category';
    }

    /**
     * {@inheritdoc}
     */
    public function relations()
    {
        return [
            'items' => [self::HAS_MANY, Item::class, 'category_id'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'softDelete' => [
                'class' => SoftDeleteBehavior::class,
                'softDeleteAttributeValues' => [
                    'is_deleted' => true,
                ],
                'useRestoreAttributeValuesAsDefaults' => true,
            ],
        ];
    }
}