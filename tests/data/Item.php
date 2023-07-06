<?php

namespace yii1tech\ar\softdelete\test\data;

use CActiveRecord;
use CDbException;
use yii1tech\ar\softdelete\SoftDeleteBehavior;

/**
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property bool $is_deleted
 * @property int $deleted_at
 *
 * @property Category $category
 */
class Item extends CActiveRecord
{
    /**
     * @var bool whether to throw {@see onDeleteExceptionClass} exception on {@see delete()}
     */
    public $throwOnDeleteException = false;
    /**
     * @var string class name of the exception to be thrown on delete.
     */
    public $onDeleteExceptionClass = CDbException::class;

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
        return 'item';
    }

    /**
     * {@inheritdoc}
     */
    public function relations()
    {
        return [
            'category' => [self::HAS_ONE, Category::class, 'category_id'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'softDeleteBehavior' => [
                'class' => SoftDeleteBehavior::class,
                'softDeleteAttributeValues' => [
                    'is_deleted' => true,
                ],
                'allowDeleteCallback' => function ($model) {
                    return $model->name === 'allow-delete';
                },
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function beforeDelete()
    {
        if ($this->throwOnDeleteException) {
            $className = $this->onDeleteExceptionClass;
            $exception = new $className('Emulation');
            throw $exception;
        }

        return parent::beforeDelete();
    }
}