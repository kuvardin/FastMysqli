# FastMysqli

Simple PHP class for working with MySQL

## Installing
```shell script
composer require "kuvardin/fast-mysqli: dev-master"
```

## Using example
### Class init
```php
<?php

use Kuvardin\FastMysqli\Mysqli;
require 'vendor/autoload.php';

$db = [
    'host' => 'localhost',
    'user' => 'kuvardin',
    'pass' => 'qwerty',
    'name' => 'testbase',
];

// Connect to MySQL server
$mysqli = new Mysqli($db['host'], $db['user'], $db['pass'], $db['name']);

// Query
$filtered_name = $mysqli->filter($_GET['name']);
$mysqli->q("DELETE FROM `users` WHERE `name` = '$filtered_name' LIMIT 1");

// Simple select 
// SELECT * FROM `users` WHERE `name` = '{$this->filter($_GET['name'])}' AND `surname` IS NOT NULL LIMIT 1
$user = $mysqli->fast_select('users', [
    'name' => $_GET['name'],
    'surname' => Mysqli::not_null(),
], 1);

// Adding a new row
// INSERT INTO `users` SET `name` = 'Maxim', `surname` = 'Kuvardin'
$mysqli->fast_add('users', [
  'name' => 'Maxim',
  'surname' => 'Kuvardin',
]);

// Updating rows
// UPDATE `users` SET `name` = 'Jonathan' WHERE `name` = 'John' LIMIT 1
$mysqli->fast_update('users', ['name' => 'Jonathan'], ['name' => 'John'], 1);

// Checking row
if ($mysqli->fast_check('users', ['name' => 'Jonathan'])) {
  echo 'Row are exists';
}

// Deleting rows
// DELETE FROM `users` WHERE `banned` IS NOT NULL LIMIT 3
$mysqli->fast_delete('users', ['banned' => Mysqli::not_null()], 3);
```
