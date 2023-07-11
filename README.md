<p align="center">
    <a href="https://github.com/yii1tech" target="_blank">
        <img src="https://avatars.githubusercontent.com/u/134691944" height="100px">
    </a>
    <h1 align="center">Application Runtime Configuration Extension for Yii 1</h1>
    <br>
</p>

This extension provides support for Yii1 ActiveRecord soft delete.

For license information check the [LICENSE](LICENSE.md)-file.

[![Latest Stable Version](https://img.shields.io/packagist/v/yii1tech/ar-softdelete.svg)](https://packagist.org/packages/yii1tech/ar-softdelete)
[![Total Downloads](https://img.shields.io/packagist/dt/yii1tech/ar-softdelete.svg)](https://packagist.org/packages/yii1tech/ar-softdelete)
[![Build Status](https://github.com/yii1tech/ar-softdelete/workflows/build/badge.svg)](https://github.com/yii1tech/ar-softdelete/actions)


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist yii1tech/ar-softdelete
```

or add

```json
"yii1tech/ar-softdelete": "*"
```

to the "require" section of your composer.json.


Usage
-----

This extension provides support for so called "soft" deletion of the ActiveRecord, which means record is not deleted
from database, but marked with some flag or status, which indicates it is no longer active, instead.

This extension provides `\yii1tech\ar\softdelete\SoftDeleteBehavior` ActiveRecord behavior for such solution support
in Yii1. You may attach it to your model class in the following way:

```php
<?php

use CActiveRecord;
use yii1tech\ar\softdelete\SoftDeleteBehavior;

class Item extends CActiveRecord
{
    public function behaviors()
    {
        return [
            'softDeleteBehavior' => [
                'class' => SoftDeleteBehavior::class,
                'softDeleteAttributeValues' => [
                    'is_deleted' => true,
                ],
            ],
        ];
    }
}
```

There are 2 ways of "soft" delete applying:
- using `softDelete()` separated method
- mutating regular `delete()` method

Usage of `softDelete()` is recommended, since it allows marking the record as "deleted", while leaving regular `delete()`
method intact, which allows you to perform "hard" delete if necessary. For example:

```php
<?php

$id = 17;
$item = Item::model()->findByPk($id);
$item->softDelete(); // mark record as "deleted"

$item = Item::model()->findByPk($id);
var_dump($item->is_deleted); // outputs "true"

$item->delete(); // perform actual deleting of the record
$item = Item::model()->findByPk($id);
var_dump($item); // outputs "null"
```

However, you may want to mutate regular ActiveRecord `delete()` method in the way it performs "soft" deleting instead
of actual removing of the record. It is a common solution in such cases as applying "soft" delete functionality for
existing code. For such functionality you should enable `\yii1tech\ar\softdelete\SoftDeleteBehavior::$replaceRegularDelete`
option in behavior configuration:

```php
<?php

use CActiveRecord;
use yii1tech\ar\softdelete\SoftDeleteBehavior;

class Item extends CActiveRecord
{
    public function behaviors()
    {
        return [
            'softDeleteBehavior' => [
                'class' => SoftDeleteBehavior::class,
                'softDeleteAttributeValues' => [
                    'is_deleted' => true
                ],
                'replaceRegularDelete' => true // mutate native `delete()` method
            ],
        ];
    }
}
```

Now invocation of the `delete()` method will mark record as "deleted" instead of removing it:

```php
<?php

$id = 17;
$item = Item::model()->findByPk($id);
$item->delete(); // no record removal, mark record as "deleted" instead

$item = Item::model()->findByPk($id);
var_dump($item->is_deleted); // outputs "true"
```


## Querying "soft" deleted records <span id="querying-soft-deleted-records"></span>

Obviously, in order to find only "deleted" or only "active" records you should add corresponding condition to your search query:

```php
<?php

// returns only not "deleted" records
$notDeletedItems = Item::model()
    ->findAll('is_deleted = 0');

// returns "deleted" records
$deletedItems = Item::model()
    ->findAll('is_deleted = 1');
```

However, you can use `\yii1tech\ar\softdelete\SoftDeleteQueryBehavior` to facilitate composition of such queries.
Once being attached ite provides methods similar to scopes for the records filtering using "soft" deleted criteria.
For example:

```php
<?php

// Find all "deleted" records:
$deletedItems = Item::model()->deleted()->findAll();

// Find all "active" records:
$notDeletedItems = Item::model()->notDeleted()->findAll();
```

You may easily create listing filter for "deleted" records using `filterDeleted()` method:

```php
<?php

// Filter records by "soft" deleted criteria:
$items = Item::model()
    ->filterDeleted(Yii::app()->request->getParam('filter_deleted'))
    ->findAll();
```

This method applies `notDeleted()` scope on empty filter value, `deleted()` - on positive filter value, and no scope (e.g.
show both "deleted" and "active" records) on negative (zero) value.

> Note: `yii1tech\ar\softdelete\SoftDeleteQueryBehavior1 has been designed to properly handle joins and avoid ambiguous
column errors, however, there still can be cases, which it will be unable to handle properly. Be prepared to specify
"soft deleted" conditions manually in case you are writing complex query, involving several tables with "soft delete" feature.

By default `yii1tech\ar\softdelete\SoftDeleteQueryBehavior` composes filter criteria for its scopes using the information from
`yii1tech\ar\softdelete\SoftDeleteBehavior::$softDeleteAttributeValues`. Thus, you may need to manually configure filter conditions
in case you are using sophisticated logic for "soft" deleted records marking. For example:

```php
<?php

use CActiveRecord;
use yii1tech\ar\softdelete\SoftDeleteBehavior;

class Item extends CActiveRecord
{
    public function behaviors()
    {
        return [
            'softDeleteBehavior' => [
                'class' => SoftDeleteBehavior::class,
                'softDeleteAttributeValues' => [
                    'statusId' => 'deleted',
                ],
                'deletedCondition' => [
                    'statusId' => 'deleted',
                ],
                'notDeletedCondition' => [
                    'statusId' => 'active',
                ],
            ],
        ];
    }
    
    // ...
}
```

> Tip: you may apply a condition, which filters "not deleted" records, to the search query as default, enabling
  `yii1tech\ar\softdelete\SoftDeleteBehavior::$autoApplyNotDeletedCondition`.

```php
<?php

use CActiveRecord;
use yii1tech\ar\softdelete\SoftDeleteBehavior;

class Item extends CActiveRecord
{
    public function behaviors()
    {
        return [
            'softDeleteBehavior' => [
                'class' => SoftDeleteBehavior::class,
                'softDeleteAttributeValues' => [
                    'is_deleted' => true,
                ],
                'autoApplyNotDeletedCondition' => true,
            ],
        ];
    }

    // ...
}

$notDeletedItems = Item::model()->findAll(); // returns only not "deleted" records

$allItems = Item::find()
    ->deleted() // applies "deleted" condition, preventing default one
    ->findAll(); // returns "deleted" records

$allItems = Item::find()
    ->filterDeleted('all') // filter all records, preventing default "not deleted" condition
    ->all(); // returns all records
```


## Smart deletion <span id="smart-deletion"></span>

Usually "soft" deleting feature is used to prevent the database history loss, ensuring data, which been in use and
perhaps have a references or dependencies, is kept in the system. However, sometimes actual deleting is allowed for
such data as well.
For example: usually user account records should not be deleted but only marked as "inactive", however if you browse
through users list and found accounts, which has been registered long ago, but don't have at least single log-in in the
system, these records have no value for the history and can be removed from database to save disk space.

You can make "soft" deletion to be "smart" and detect, if the record can be removed from the database or only marked as "deleted".
This can be done via `\yii1tech\ar\softdelete\SoftDeleteBehavior::$allowDeleteCallback`. For example:

```php
<?php
 
use CActiveRecord;
use yii1tech\ar\softdelete\SoftDeleteBehavior;

class User extends CActiveRecord
{
    public function behaviors()
    {
        return [
            'softDeleteBehavior' => [
                'class' => SoftDeleteBehavior::class,
                'softDeleteAttributeValues' => [
                    'is_deleted' => true
                ],
                'allowDeleteCallback' => function ($user) {
                    return $user->last_login_date === null; // allow to delete user, if he has never logged in
                }
            ],
        ];
    }
}

$user = User::model()->find('last_login_date IS NULL');
$user->softDelete(); // removes the record!!!

$user = User::find()->find('last_login_date IS NOT NULL');
$user->softDelete(); // marks record as "deleted"
```

`\yii1tech\ar\softdelete\SoftDeleteBehavior::$allowDeleteCallback` logic is applied in case `\yii1tech\ar\softdelete\SoftDeleteBehavior::$replaceRegularDelete`
is enabled as well.


## Handling foreign key constraints <span id="handling-foreign-key-constraints"></span>

In case of usage of the relational database, which supports foreign keys, like MySQL, PostgreSQL etc., "soft" deletion
is widely used for keeping foreign keys consistence. For example: if user performs a purchase at the online shop, information
about this purchase should remain in the system for the future bookkeeping. The DDL for such data structure may look like
following one:

```sql
CREATE TABLE `customer`
(
   `id` integer NOT NULL AUTO_INCREMENT,
   `name` varchar(64) NOT NULL,
   `address` varchar(64) NOT NULL,
   `phone` varchar(20) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE InnoDB;

CREATE TABLE `purchase`
(
   `id` integer NOT NULL AUTO_INCREMENT,
   `customer_id` integer NOT NULL,
   `item_id` integer NOT NULL,
   `amount` integer NOT NULL,
    PRIMARY KEY (`id`)
    FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `item` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
) ENGINE InnoDB;
```

Thus, while set up a foreign key from 'purchase' to 'user', 'ON DELETE RESTRICT' mode is used. So on attempt to delete
a user record, which have at least one purchase, a database error will occur. However, if user record have no external
reference, it can be deleted.

Usage of `\yii1tech\ar\softdelete\SoftDeleteBehavior::$allowDeleteCallback` for such use case is not very practical.
It will require performing extra queries to determine, if external references exist or not, eliminating the benefits of
the foreign keys database feature.

Method `\yii1tech\ar\softdelete\SoftDeleteBehavior::safeDelete()` attempts to invoke regular `CBaseActiveRecord::delete()`
method, and, if it fails with exception, falls back to `yii1tech\ar\softdelete\SoftDeleteBehavior::softDelete()`.

```php
<?php

// if there is a foreign key reference :
$customer = Customer::model()->findByPk(15);
var_dump(count($customer->purchases)); // outputs; "1"
$customer->safeDelete(); // performs "soft" delete!
var_dump($customer->isDeleted) // outputs: "true"

// if there is NO foreign key reference :
$customer = Customer::model()->findByPk(53);
var_dump(count($customer->purchases)); // outputs; "0"
$customer->safeDelete(); // performs actual delete!
$customer = Customer::model()->findByPk(53);
var_dump($customer); // outputs: "null"
```

By default `safeDelete()` method catches `\CDbException` exception, which means soft deleting will be
performed on foreign constraint violation DB exception. You may specify another exception class here to customize fallback
error level. For example: usage of `\Throwable` will cause soft-delete fallback on any error during regular deleting.


## Record restoration <span id="record-restoration"></span>

At some point you may want to "restore" records, which have been marked as "deleted" in the past.
You may use `restore()` method for this:

```php
<?php

$id = 17;
$item = Item::model()->findByPk($id);
$item->softDelete(); // mark record as "deleted"

$item = Item::model()->findByPk($id);
$item->restore(); // restore record
var_dump($item->is_deleted); // outputs "false"
```

By default, attribute values, which should be applied for record restoration are automatically detected from `\yii1tech\ar\softdelete\SoftDeleteBehavior::$softDeleteAttributeValues`,
however it is better you specify them explicitly via `\yii1tech\ar\softdelete\SoftDeleteBehavior::$restoreAttributeValues`.

> Tip: if you enable `\yii1tech\ar\softdelete\SoftDeleteBehavior::$useRestoreAttributeValuesAsDefaults`, attribute values,
  which marks restored record, will be automatically applied at new record insertion.


## Events <span id="events"></span>

By default `\yii1tech\ar\softdelete\SoftDeleteBehavior::softDelete()` triggers `\CActiveRecord::onBeforeDelete`
and `\CActiveRecord::onAfterDelete` events in the same way they are triggered at regular `delete()`.

Also `\yii1tech\ar\softdelete\SoftDeleteBehavior` allows you to hook on soft-delete process defining specific methods at the owner ActiveRecord class:

- `beforeSoftDelete()` - triggered before "soft" delete is made.
- `afterSoftDelete()` - triggered after "soft" delete is made.
- `beforeRestore()` - triggered before record is restored from "deleted" state.
- `afterRestore()` - triggered after record is restored from "deleted" state.

For example:

```php
<?php

use CActiveRecord;
use yii1tech\ar\softdelete\SoftDeleteBehavior;

class Item extends CActiveRecord
{
    public function behaviors()
    {
        return [
            'softDeleteBehavior' => [
                'class' => SoftDeleteBehavior::class,
                // ...
            ],
        ];
    }

    public function beforeSoftDelete(): bool
    {
        $this->deleted_at = time(); // log the deletion date
        
        return true;
    }

    public function beforeRestore(): bool
    {
        return $this->deleted_at > (time() - 3600); // allow restoration only for the records, being deleted during last hour
    }
}
```
