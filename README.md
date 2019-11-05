# FastMysqli

Simple PHP class for working with MySQL

## Using example
### Class init
```
<?php
$db => [
    'host' => 'localhost',
    'user' => 'kuvardin',
    'pass' => 'qwerty',
    'name' => 'testbase',
];

$mysqli = new FastMysqli($db['host'], $db['user'], $db['pass'], $db['name']);
```

### Simple query
```
$filtered_name = $mysqli->filter($_GET['name']);
$user = $mysqli->q("SELECT * FROM `users` WHERE `name` = '$filtered_name' LIMIT 1");
```

### Simple select
```
// SELECT * FROM `users` WHERE `name` = '{$mysqli->filter($_GET['name'])}' LIMIT 1
$user = $mysqli->fast_select('users', ['name' => $_GET['name']], 1);
```

### Adding the new row
```
// INSERT INTO `users` SET `name` = 'Maxim', `surname` = 'Kuvardin'
$mysqli->fast_add_row('users', [
  'name' => 'Maxim',
  'surname' => 'Kuvardin',
]);
```

### Updating rows
```
// UPDATE `users` SET `name` = 'Jonathan' WHERE `name` = 'John' LIMIT 1
$mysqli->fast_update_row('users', ['name' => 'John'], ['name' => 'Jonathan'], 1);
```

### Checking row
```
if ($mysqli->fast_check_row('users', ['name' => 'Jonathan'])) {
  echo "Row are exists";
}
```

### Deleting rows
```
// DELETE FROM `users` WHERE `banned` IS NOT NULL LIMIT 3
$mysqli->fast_delete_row('users', ['banned' => FastMysqli::NOT_NULL], 3);
```
