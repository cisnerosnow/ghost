# Ghost

<div style="text-align:center"><img src="https://oneclicksoftwaresolutions.com/ghost-logo.png" /></div>

<p style="text-align:center">Ghost is a PHP nano framework for quickly writing REST APIs.</p>

---

## Requirements

- PHP 7.0+
- MySQLi extension (for MySQL), MSSQL driver, or OCI8 (for Oracle)

---

## Quick start

```php
require 'ghost.php';

$ghost = new Ghost();
$ghost->connect('localhost', 'root', '', 'my_db');

$ghost->service('post',   'employee', ['name' => 'text', 'lastname' => 'text']);
$ghost->service('get',    'employee', ['name' => 'text']);
$ghost->service('put',    'employee', ['name' => 'text', 'lastname' => 'text', 'id' => 'key']);
$ghost->service('delete', 'employee', ['id' => 'key']);

$ghost->run();
```

---

## Setup

### `connect($host, $user, $pass, $db_name)`

Connects to the database.

```php
$ghost->connect('localhost', 'root', 'secret', 'my_db');
```

### `set_db_type($type)`

Selects the database engine. Default is `mysql`.

| Value | Engine |
|-------|--------|
| `mysql` | MySQL / MariaDB |
| `mssql` | Microsoft SQL Server |
| `oracle` | Oracle |

```php
$ghost->set_db_type('oracle');
```

---

## Registering endpoints — `service()`

```php
$ghost->service($method, $option, $rules, $function);
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$method` | string | HTTP method: `post`, `get`, `put`, `delete`, or `crud` |
| `$option` | string | Route/table name |
| `$rules` | array\|null | Validation rules (optional) |
| `$function` | callable\|null | Custom callback (optional) |

When no `$function` is given, Ghost automatically runs the corresponding SQL operation against `$option` as the table name.

### CRUD shorthand

Registers all four methods at once:

```php
$ghost->service('crud', 'employee', [
    'name'     => 'text',
    'lastname' => 'text',
    'id'       => 'key',
]);
```

### Custom callback

```php
$ghost->service('post', 'login', ['email' => 'email', 'password' => 'text'], function($ghost) {
    $email    = $ghost->param->email;
    $password = $ghost->param->password;
    // ... custom logic
    $ghost->response(['token' => '...'], 200);
});
```

Inside the callback, `$ghost->param` is an object and `$ghost->params` is an array with all validated parameters.

---

## Validation rules

Defined as an associative array `['field' => 'type']`.

| Type | Description |
|------|-------------|
| `text` | Any string |
| `int` | Numeric value |
| `bool` | Boolean (`true` / `false`) |
| `email` | Valid email address |
| `file` | File upload (`$_FILES`) |
| `json` | Valid JSON string |
| `array` | PHP array |
| `key` | Numeric identifier — marks the primary key for `put`/`delete` |

You can also pass a callable as the type for custom validation:

```php
$ghost->service('post', 'register', [
    'username' => function($g) {
        // $g->username contains the value, $g->con is the DB connection
        return strlen($g->username) >= 3;
    },
]);
```

Or use the extended rule array for combined checks:

```php
'token' => ['type' => 'text', 'length' => 32, 'function' => $myValidator]
```

---

## Making requests

Ghost supports two syntaxes.

### Classic syntax

Send `option` and `params` as request parameters:

```js
// POST
fetch('api.php', {
    method: 'POST',
    body: new URLSearchParams({ option: 'employee', 'params[name]': 'Martin', 'params[lastname]': 'Doe' })
});

// GET
fetch('api.php?option=employee&params[name]=Martin');

// PUT / DELETE — same as GET params in body
fetch('api.php', {
    method: 'PUT',
    body: new URLSearchParams({ option: 'employee', 'params[id]': 1, 'params[name]': 'John' })
});
```

### Short syntax

Use the route name directly as the first query string key:

```
GET /api.php?employee&name=Martin
```

The first key in `$_GET` is treated as the `option`, and the remaining params are passed as-is.

---

## Response methods

### `response($msg, $code)`

Sends a JSON response and stops execution.

```php
$ghost->response(['user' => $user], 200);
$ghost->response('Unauthorized', 401);
```

Response body format: `{"message": ..., "code": ...}`

### `resp($msg, $code)`

Simpler alternative — sends raw JSON or plain text without wrapping.

```php
$ghost->resp(['data' => $rows], 200);
$ghost->resp(FALSE); // sends 500
```

### `responseText($msg, $code)`

Sends a plain text response.

```php
$ghost->responseText('OK', 200);
```

### `jsonEncode($arr)`

Sends an array as JSON and stops execution.

```php
$ghost->jsonEncode($myArray);
```

### `boolResponse($bool)`

Sends 200 if `$bool` is `TRUE`, 500 if `FALSE`.

```php
$ghost->boolResponse($result);
```

---

## Database helpers

These can be used freely inside custom callbacks.

### `get($table, $fields, $where, $limit, $orderBy)`

```php
$rows = $ghost->get('employee', ['id', 'name'], ['name' => 'Martin'], 10);
```

Returns an array of rows, or `FALSE` if none found.

### `getAll($table, $fields, $where, $orderBy)`

Like `get()` but without a limit.

```php
$all = $ghost->getAll('employee', ['id', 'name']);
```

### `post($table, $params)`

Inserts a row.

```php
$ghost->post('employee', ['name' => 'Martin', 'lastname' => 'Doe']);
```

### `put($table, $params, $where, $limit)`

Updates rows matching `$where`.

```php
$ghost->put('employee', ['name' => 'John'], ['id' => 5]);
```

### `delete($table, $where, $limit)`

Deletes rows matching `$where`.

```php
$ghost->delete('employee', ['id' => 5]);
```

### `query($sql)`

Runs a raw SQL query.

```php
$res = $ghost->query("SELECT * FROM employee WHERE active = 1");
$rows = $ghost->queryToArray($res);
```

### `joinData($arr1, $fields1, $arr2, $fields2, $replace)`

Merges two result arrays by a shared key — like a PHP-side JOIN.

---

## Utilities

### `createToken($length)`

Generates a cryptographically secure random token.

```php
$token = $ghost->createToken(32);
```

### `save_file($fileParam, $path)`

Moves an uploaded file to `$path` (or `uploads/` by default) with a random name.

```php
$path = $ghost->save_file($_FILES['avatar'], 'uploads/avatars/');
```

### `validateDate($date, $format)`

Returns `TRUE` if the string matches the given date format.

```php
$ghost->validateDate('2024-01-15');          // TRUE
$ghost->validateDate('15/01/2024', 'd/m/Y'); // TRUE
```

---

## Properties available inside callbacks

| Property | Type | Description |
|----------|------|-------------|
| `$ghost->params` | array | All validated parameters |
| `$ghost->param` | object | Same as `params` but as an object |
| `$ghost->option` | string | The matched route name |
| `$ghost->method` | string | HTTP method in lowercase |
| `$ghost->con` | resource | Active database connection |
| `$ghost->files` | array | Names of uploaded file fields |
