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

```
