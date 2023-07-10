<?php

namespace yii1tech\ar\softdelete;

use CBehavior;
use CDbCriteria;
use CDbException;
use CDbExpression;
use CModelEvent;
use LogicException;

/**
 * SoftDeleteBehavior provides support for "soft" delete of ActiveRecord models as well as restoring them
 * from "deleted" state.
 *
 * ```php
 * class Item extends CActiveRecord
 * {
 *     public function behaviors()
 *     {
 *         return [
 *             'softDeleteBehavior' => [
 *                 'class' => SoftDeleteBehavior::class,
 *                 'softDeleteAttributeValues' => [
 *                     'is_deleted' => true,
 *                 ],
 *             ],
 *         ];
 *     }
 * }
 * ```
 *
 * Basic usage example:
 *
 * ```php
 * $item = Item::model()->findByPk($id);
 * $item->softDelete(); // mark record as "deleted"
 *
 * $item = Item::model()->findByPk($id);
 * var_dump($item->is_deleted); // outputs "true"
 *
 * $item->restore(); // restores record from "deleted"
 *
 * $item = Item::model()->findByPk($id);
 * var_dump($item->is_deleted); // outputs "false"
 * ```
 *
 * @property \CActiveRecord $owner behavior owner.
 * @property bool $replaceRegularDelete whether to perform soft delete instead of regular delete.
 * If enabled {@see \CActiveRecord::delete()} will perform soft deletion instead of actual record deleting.
 * @property bool $useRestoreAttributeValuesAsDefaults whether to use {@see restoreAttributeValues} as defaults on record insertion.
 * @property array $deletedCondition filter condition for 'soft-deleted' records.
 * @property array $notDeletedCondition filter condition for not 'soft-deleted' records.
 * @property bool $autoApplyNotDeletedCondition whether to automatically apply {@see notDeletedCondition} before find.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class SoftDeleteBehavior extends CBehavior
{
    /**
     * @var bool whether to perform soft delete instead of regular delete.
     * If enabled {@see \CActiveRecord::delete()} will perform soft deletion instead of actual record deleting.
     */
    protected $_replaceRegularDelete = false;
    /**
     * @var array values of the owner attributes, which should be applied on soft delete, in format: [attributeName => attributeValue].
     * Those may raise a flag:
     *
     * ```php
     * ['is_deleted' => true]
     * ```
     *
     * or switch status:
     *
     * ```php
     * ['status_id' => Item::STATUS_DELETED]
     * ```
     *
     * Attribute value can be a callable:
     *
     * ```php
     * ['deleted_at' => function ($model) {return time();}]
     * ```
     */
    public $softDeleteAttributeValues = [
        'is_deleted' => true,
    ];
    /**
     * @var array|null values of the owner attributes, which should be applied on restoration from "deleted" state,
     * in format: `[attributeName => attributeValue]`. If not set value will be automatically detected from {@see softDeleteAttributeValues}.
     */
    public $restoreAttributeValues;
    /**
     * @var bool whether to invoke owner {@see \CActiveRecord::beforeDelete()} and {@see \CActiveRecord::afterDelete()}
     * while performing soft delete. This option affects only {@see softDelete()} method.
     */
    public $invokeDeleteEvents = true;
    /**
     * @var callable|null callback, which execution determines if record should be "hard" deleted instead of being marked
     * as deleted. Callback should match following signature: `bool function(\CActiveRecord $model)`
     * For example:
     *
     * ```php
     * function ($user) {
     *     return $user->lastLoginDate === null;
     * }
     * ```
     */
    public $allowDeleteCallback;
    /**
     * @var string class name of the exception, which should trigger a fallback to {@see softDelete()} method from {@see safeDelete()}.
     * By default {@see \CDbException} is used, which means soft deleting will be performed on foreign constraint
     * violation DB exception.
     * You may specify another exception class here to customize fallback error level. For example: usage of {@see \Throwable}
     * will cause soft-delete fallback on any error during regular deleting.
     * @see safeDelete()
     */
    public $deleteFallbackException = CDbException::class;
    /**
     * @var bool whether to use {@see restoreAttributeValues} as defaults on record insertion.
     */
    private $_useRestoreAttributeValuesAsDefaults = false;
    /**
     * @var array filter condition for 'soft-deleted' records.
     */
    private $_deletedCondition;
    /**
     * @var array filter condition for not 'soft-deleted' records.
     */
    private $_notDeletedCondition;
    /**
     * @var bool whether to automatically apply {@see notDeletedCondition} before find.
     */
    private $_autoApplyNotDeletedCondition = false;
    /**
     * @var bool indicates whether "soft-delete" related condition has been already applied or not.
     */
    private $isSoftDeleteConditionApplied = false;

    /**
     * @return bool whether to perform soft delete instead of regular delete.
     */
    public function getReplaceRegularDelete(): bool
    {
        return $this->_replaceRegularDelete;
    }

    /**
     * @param bool $replaceRegularDelete whether to perform soft delete instead of regular delete.
     * @return \CActiveRecord|null owner record.
     */
    public function setReplaceRegularDelete(bool $replaceRegularDelete)
    {
        $isEnabled = is_object($this->owner) && $this->getEnabled();
        if ($isEnabled) {
            $this->setEnabled(false);
        }

        $this->_replaceRegularDelete = $replaceRegularDelete;

        if ($isEnabled) {
            $this->setEnabled(true);
        }

        return $this->owner;
    }

    /**
     * @return bool whether to use {@see restoreAttributeValues} as defaults on record insertion.
     */
    public function getUseRestoreAttributeValuesAsDefaults(): bool
    {
        return $this->_useRestoreAttributeValuesAsDefaults;
    }

    /**
     * @param bool $useRestoreAttributeValuesAsDefaults whether to use {@see restoreAttributeValues} as defaults on record insertion.
     * @return \CActiveRecord|null owner record.
     */
    public function setUseRestoreAttributeValuesAsDefaults(bool $useRestoreAttributeValuesAsDefaults)
    {
        $isEnabled = is_object($this->owner) && $this->getEnabled();
        if ($isEnabled) {
            $this->setEnabled(false);
        }

        $this->_useRestoreAttributeValuesAsDefaults = $useRestoreAttributeValuesAsDefaults;

        if ($isEnabled) {
            $this->setEnabled(true);
        }

        return $this->owner;
    }

    /**
     * @return array filter condition for 'soft-deleted' records.
     */
    public function getDeletedCondition()
    {
        if ($this->_deletedCondition === null) {
            $this->_deletedCondition = $this->defaultDeletedCondition();
        }

        return $this->_deletedCondition;
    }

    /**
     * @param array $deletedCondition filter condition for 'soft-deleted' records.
     * @return \CActiveRecord|null owner record.
     */
    public function setDeletedCondition($deletedCondition)
    {
        $this->_deletedCondition = $deletedCondition;

        return $this->owner;
    }

    /**
     * @return array filter condition for not 'soft-deleted' records.
     */
    public function getNotDeletedCondition()
    {
        if ($this->_notDeletedCondition === null) {
            $this->_notDeletedCondition = $this->defaultNotDeletedCondition();
        }

        return $this->_notDeletedCondition;
    }

    /**
     * @param array $notDeletedCondition filter condition for not 'soft-deleted' records.
     * @return \CActiveRecord|null owner record.
     */
    public function setNotDeletedCondition($notDeletedCondition)
    {
        $this->_notDeletedCondition = $notDeletedCondition;

        return $this->owner;
    }

    /**
     * @return bool whether to automatically apply {@see notDeletedCondition} before find.
     */
    public function getAutoApplyNotDeletedCondition(): bool
    {
        return $this->_autoApplyNotDeletedCondition;
    }

    /**
     * @param bool $autoApplyNotDeletedCondition whether to automatically apply {@see notDeletedCondition} before find.
     * @return \CActiveRecord|null owner record.
     */
    public function setAutoApplyNotDeletedCondition(bool $autoApplyNotDeletedCondition)
    {
        $isEnabled = is_object($this->owner) && $this->getEnabled();
        if ($isEnabled) {
            $this->setEnabled(false);
        }

        $this->_autoApplyNotDeletedCondition = $autoApplyNotDeletedCondition;

        $this->isSoftDeleteConditionApplied = false;

        if ($isEnabled) {
            $this->setEnabled(true);
        }

        return $this->owner;
    }

    // Soft Delete :

    /**
     * Marks the owner as deleted.
     *
     * @return bool whether the deletion is successful.
     */
    public function softDelete(): bool
    {
        if ($this->isDeleteAllowed()) {
            return $this->owner->delete();
        }

        if ($this->invokeDeleteEvents && !$this->owner->evaluateExpression('$this->beforeDelete()')) {
            return false;
        }

        $result = $this->softDeleteInternal();

        if ($this->invokeDeleteEvents) {
            $this->owner->evaluateExpression('$this->afterDelete()');
        }

        return $result;
    }

    /**
     * Marks the owner as deleted.
     *
     * @return bool whether the update is successful.
     */
    protected function softDeleteInternal(): bool
    {
        $result = false;

        if ($this->beforeSoftDelete()) {
            $attributes = [];
            foreach ($this->softDeleteAttributeValues as $attribute => $value) {
                if (!is_scalar($value) && is_callable($value)) {
                    $value = call_user_func($value, $this->owner);
                }
                $attributes[$attribute] = $value;
            }

            $result = $this->updateAttributes($attributes);

            $this->afterSoftDelete();
        }

        return $result;
    }

    /**
     * Updates owner attributes.
     *
     * @param array $attributes the owner attributes (names or name-value pairs) to be updated
     * @return int the number of rows affected.
     */
    private function updateAttributes(array $attributes)
    {
        $owner = $this->owner;

        $result = $owner->updateByPk($owner->getOldPrimaryKey(), $attributes);

        if ($result > 0) {
            foreach ($attributes as $name => $value) {
                $owner->setAttribute($name, $value);
            }
        }

        return $result;
    }

    /**
     * This method is invoked before soft deleting a record.
     * The default implementation raises invokes owner's `beforeSoftDelete()` method, if it does exist.
     *
     * @return bool whether the record should be deleted. Defaults to true.
     */
    public function beforeSoftDelete(): bool
    {
        if (method_exists($this->owner, 'beforeSoftDelete')) {
            if (!$this->owner->beforeSoftDelete()) {
                return false;
            }
        }

        return true;
    }

    /**
     * This method is invoked after soft deleting a record.
     * The default implementation raises invokes owner's `afterSoftDelete()` method, if it does exist.
     * You may override this method to do postprocessing after the record is deleted.
     * Make sure you call the parent implementation so that the event is raised properly.
     */
    public function afterSoftDelete(): void
    {
        if (method_exists($this->owner, 'afterSoftDelete')) {
            $this->owner->afterSoftDelete();
        }
    }

    /**
     * @return bool whether owner "hard" deletion allowed or not.
     */
    protected function isDeleteAllowed()
    {
        if ($this->allowDeleteCallback === null) {
            return false;
        }

        return call_user_func($this->allowDeleteCallback, $this->owner);
    }

    // Restore :

    /**
     * Restores record from "deleted" state, after it has been "soft" deleted.
     *
     * @return bool whether the restoration is successful.
     */
    public function restore(): bool
    {
        $result = false;

        if ($this->beforeRestore()) {
            $result = $this->restoreInternal();
            $this->afterRestore();
        }

        return $result;
    }

    /**
     * Performs restoration for soft-deleted record.
     *
     * @return bool whether the update is successful.
     */
    protected function restoreInternal(): bool
    {
        $restoreAttributeValues = $this->detectRestoreAttributeValues();

        $attributes = [];
        foreach ($restoreAttributeValues as $attribute => $value) {
            if (!is_scalar($value) && is_callable($value)) {
                $value = call_user_func($value, $this->owner);
            }
            $attributes[$attribute] = $value;
        }

        return $this->updateAttributes($attributes);
    }

    /**
     * Detects values of the owner attributes, which should be applied on restoration from "deleted" state.
     *
     * @return array values of the owner attributes in format `[attributeName => attributeValue]`
     * @throws \LogicException if unable to detect restore attribute values.
     */
    private function detectRestoreAttributeValues(): array
    {
        if ($this->restoreAttributeValues !== null) {
            return $this->restoreAttributeValues;
        }

        $restoreAttributeValues = [];
        foreach ($this->softDeleteAttributeValues as $name => $value) {
            if (is_bool($value)) {
                $restoreValue = !$value;
            } elseif (is_int($value)) {
                if ($value === 1) {
                    $restoreValue = 0;
                } elseif ($value === 0) {
                    $restoreValue = 1;
                } else {
                    $restoreValue = $value + 1;
                }
            } elseif (!is_scalar($value) && is_callable($value)) {
                $restoreValue = null;
            } elseif ($value instanceof CDbExpression) {
                $restoreValue = null;
            } else {
                throw new LogicException('Unable to automatically determine restore attribute values, "' . get_class($this) . '::$restoreAttributeValues" should be explicitly set.');
            }

            $restoreAttributeValues[$name] = $restoreValue;
        }

        return $restoreAttributeValues;
    }

    /**
     * This method is invoked before record is restored from "deleted" state.
     * The default implementation raises invokes owner's `beforeRestore()` method, if it does exist.
     *
     * @return bool whether the record should be restored. Defaults to `true`.
     */
    public function beforeRestore(): bool
    {
        if (method_exists($this->owner, 'beforeRestore')) {
            if (!$this->owner->beforeRestore()) {
                return false;
            }
        }

        return true;
    }

    /**
     * This method is invoked after record is restored from "deleted" state.
     * The default implementation raises invokes owner's `afterRestore()` method, if it does exist.
     * You may override this method to do postprocessing after the record is restored.
     * Make sure you call the parent implementation so that the event is raised properly.
     */
    public function afterRestore(): void
    {
        if (method_exists($this->owner, 'afterRestore')) {
            $this->owner->afterRestore();
        }
    }

    // Safe Delete:

    /**
     * Attempts to perform regular {@see \CActiveRecord::delete()}, if it fails with exception, falls back to {@see softDelete()}.
     * Regular deleting attempt will be enclosed in transaction with rollback in case of failure.
     * @return false|int number of affected rows.
     * @throws \Throwable on failure.
     */
    public function safeDelete()
    {
        $transaction = $this->owner->getDbConnection()->beginTransaction();

        try {
            $result = $this->owner->delete();

            $transaction->commit();

            return $result;
        } catch (\Throwable $exception) {
            $transaction->rollback();

            if ($exception instanceof $this->deleteFallbackException) {
                return $this->softDeleteInternal();
            }

            throw $exception;
        }
    }

    // Query:

    /**
     * Creates search criteria for fetching "soft-deleted" records.
     *
     * @return \CDbCriteria search criteria instance.
     */
    protected function createDeletedFindCriteria()
    {
        foreach ($this->getDeletedCondition() as $attribute => $value) {
            if (!is_scalar($value) && is_callable($value)) {
                $value = call_user_func($value, $this->owner);
            }
            $attributes[$attribute] = $value;
        }

        $criteria = new CDbCriteria();
        $criteria->addColumnCondition($attributes);

        return $criteria;
    }

    /**
     * Creates search criteria for fetching NOT "soft-deleted" records.
     *
     * @return \CDbCriteria search criteria instance.
     */
    protected function createNotDeletedFindCriteria()
    {
        foreach ($this->getNotDeletedCondition() as $attribute => $value) {
            if (!is_scalar($value) && is_callable($value)) {
                $value = call_user_func($value, $this->owner);
            }
            $attributes[$attribute] = $value;
        }

        $criteria = new CDbCriteria();
        $criteria->addColumnCondition($attributes);

        return $criteria;
    }

    /**
     * Generates default filter condition for 'deleted' records.
     * @see deletedCondition
     *
     * @return array filter condition.
     */
    protected function defaultDeletedCondition()
    {
        $condition = [];
        foreach ($this->softDeleteAttributeValues as $attribute => $value) {
            if (!is_scalar($value) && is_callable($value)) {
                $value = call_user_func($value, $this);
            }
            $condition[$attribute] = $value;
        }

        return $condition;
    }

    /**
     * Generates default filter condition for not 'deleted' records.
     * @see notDeletedCondition
     *
     * @return array filter condition.
     * @throws \LogicException on invalid configuration.
     */
    protected function defaultNotDeletedCondition()
    {
        $condition = [];

        foreach ($this->detectRestoreAttributeValues() as $attribute => $value) {
            if (is_bool($value)) {
                $restoreValue = $value;
            } elseif (is_int($value)) {
                $restoreValue = $value;
            } elseif (!is_scalar($value) && is_callable($value)) {
                $restoreValue = call_user_func($value, $this);
            } elseif ($value instanceof CDbExpression) {
                $restoreValue = $value;
            } else {
                throw new LogicException('Unable to automatically determine not delete condition, "' . get_class($this) . '::$notDeletedCondition" should be explicitly set.');
            }

            $condition[$attribute] = $restoreValue;
        }

        return $condition;
    }

    /**
     * Filters query to return only 'soft-deleted' records.
     *
     * @return \CActiveRecord|static query instance.
     */
    public function deleted()
    {
        $this->owner->getDbCriteria()->mergeWith($this->createDeletedFindCriteria());

        $this->isSoftDeleteConditionApplied = true;

        return $this->owner;
    }

    /**
     * Filters query to return only not 'soft-deleted' records.
     *
     * @return \CActiveRecord|static query instance.
     */
    public function notDeleted()
    {
        $this->owner->getDbCriteria()->mergeWith($this->createNotDeletedFindCriteria());

        $this->isSoftDeleteConditionApplied = true;

        return $this->owner;
    }

    /**
     * Applies `deleted()` or `notDeleted()` to the query regarding passed filter value.
     * If an empty value is passed - only not deleted records will be queried.
     * If value matching not empty int passed - only deleted records will be queried.
     * If not empty value matching int zero passed (e.g. `0`, `'0'`, `'all'`, `false`) - all records will be queried.
     *
     * @param mixed $deleted filter value.
     * @return \CActiveRecord|static
     */
    public function filterDeleted($deleted)
    {
        if ($deleted === '' || $deleted === null || $deleted === []) {
            return $this->notDeleted();
        }

        if ((int) $deleted) {
            return $this->deleted();
        }

        $this->isSoftDeleteConditionApplied = true;

        return $this->owner;
    }

    // Event Handlers:

    /**
     * {@inheritdoc}
     */
    public function events(): array
    {
        $events = [];

        if ($this->getReplaceRegularDelete()) {
            $events['onBeforeDelete'] = 'beforeDelete';
        }

        if ($this->getUseRestoreAttributeValuesAsDefaults()) {
            $events['onBeforeSave'] = 'beforeSave';
        }

        if ($this->getAutoApplyNotDeletedCondition()) {
            $events['onBeforeFind'] = 'beforeFind';
        }

        return $events;
    }

    /**
     * @see \CActiveRecord::onBeforeDelete()
     *
     * @param \CModelEvent $event event instance.
     * @return void
     */
    public function beforeDelete(CModelEvent $event): void
    {
        if (!$this->isDeleteAllowed()) {
            $this->softDeleteInternal();
            $event->isValid = false;
        }
    }

    /**
     * Handles owner 'beforeSave' owner event, applying {@see restoreAttributeValues} to the new record.
     * @param \CModelEvent $event event instance.
     */
    public function beforeSave(CModelEvent $event): void
    {
        if (!$this->owner->getIsNewRecord()) {
            return;
        }

        foreach ($this->detectRestoreAttributeValues() as $attribute => $value) {
            if (isset($this->owner->{$attribute})) {
                continue;
            }

            if (!is_scalar($value) && is_callable($value)) {
                $value = call_user_func($value, $this->owner);
            }
            $this->owner->{$attribute} = $value;
        }
    }

    /**
     * @see \CActiveRecord::onBeforeFind()
     *
     * @param \CModelEvent $event event instance.
     * @return void
     */
    public function beforeFind(CModelEvent $event): void
    {
        if (!$this->isSoftDeleteConditionApplied) {
            $this->notDeleted();
        }

        $this->isSoftDeleteConditionApplied = false;
    }
}