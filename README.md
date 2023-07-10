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