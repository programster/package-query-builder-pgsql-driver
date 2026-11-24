# Rules and operators

- [The JSON](#the-json)
- [Groups](#groups)
- [Operators](#operators)
- [Values](#values)
- [Allowed fields](#allowed-fields)


## The JSON

The driver takes the JSON that jQuery QueryBuilder's `getRules()` gives. It's a group of rules, each of which is either
a rule or another group:

```json
{
    "condition": "AND",
    "rules": [
        {"id": "name", "field": "name", "type": "string", "input": "text", "operator": "equal", "value": "bob"},
        {"condition": "OR", "rules": [ ... ]}
    ],
    "valid": true
}
```

A rule needs a `field` (the column), an `operator`, and a `value` for every operator other than `is_empty`,
`is_not_empty`, `is_null` and `is_not_null`. Its other keys, such as `id`, `type` and `input`, aren't used.

QueryBuilder sets `valid` to false when the user hasn't finished filling the rules in. Rules marked as invalid are
rejected with an `ExceptionInvalidRules`. JSON without a `valid` key is accepted.


## Groups

A group's `condition` is `AND`, for all of its rules to match, or `OR`, for any of them. It must be in capitals, as
QueryBuilder gives it. A group needs at least one rule.

A group within a group is put in brackets, so the rules are combined in the order the user built them:

```php
<?php

use Programster\QueryBuilderPgsqlDriver\AllowedFields;
use Programster\QueryBuilderPgsqlDriver\PgSqlDriver;

$driver = new PgSqlDriver($conn, AllowedFields::createUsingFields('name', 'age', 'code'));

$json = '{"condition": "AND", "rules": [
    {"field": "age", "operator": "less", "value": 20},
    {"condition": "OR", "rules": [
        {"field": "name", "operator": "equal", "value": "bob"},
        {"field": "name", "operator": "equal", "value": "carol"}
    ]}
]}';

$sql = $driver->getSql($json);
// $sql is: "age" < 20 AND ("name" = 'bob' OR "name" = 'carol')
```

A group with `"not": true` (from QueryBuilder's NOT group plugin) matches the rows its rules don't:

```php
$json = '{"condition": "OR", "not": true, "rules": [
    {"field": "name", "operator": "equal", "value": "bob"},
    {"field": "name", "operator": "equal", "value": "carol"}
]}';

$sql = $driver->getSql($json);
// $sql is: NOT ("name" = 'bob' OR "name" = 'carol')
```


## Operators

These are QueryBuilder's operators, and the SQL each one gives. Each has a case in the `Operator` enum, e.g.
`Operator::BEGINS_WITH`, whose value is its name in the JSON.

| Operator           | Value                       | SQL                                   |
|--------------------|-----------------------------|---------------------------------------|
| `equal`            | one value                   | `"field" = 'value'`                   |
| `not_equal`        | one value                   | `"field" != 'value'`                  |
| `in`               | a list of one or more       | `"field" IN ('a','b')`                |
| `not_in`           | a list of one or more       | `"field" NOT IN ('a','b')`            |
| `less`             | one value                   | `"field" < 5`                         |
| `less_or_equal`    | one value                   | `"field" <= 5`                        |
| `greater`          | one value                   | `"field" > 5`                         |
| `greater_or_equal` | one value                   | `"field" >= 5`                        |
| `between`          | a list of exactly two       | `"field" BETWEEN 5 AND 10`            |
| `not_between`      | a list of exactly two       | `"field" NOT BETWEEN 5 AND 10`        |
| `begins_with`      | a string or number          | `"field" LIKE 'value%'`               |
| `not_begins_with`  | a string or number          | `"field" NOT LIKE 'value%'`           |
| `contains`         | a string or number          | `"field" LIKE '%value%'`              |
| `not_contains`     | a string or number          | `"field" NOT LIKE '%value%'`          |
| `ends_with`        | a string or number          | `"field" LIKE '%value'`               |
| `not_ends_with`    | a string or number          | `"field" NOT LIKE '%value'`           |
| `is_empty`         | none                        | `("field" IS NULL OR "field" = '')`   |
| `is_not_empty`     | none                        | `("field" IS NOT NULL AND "field" != '')` |
| `is_null`          | none                        | `"field" IS NULL`                     |
| `is_not_null`      | none                        | `"field" IS NOT NULL`                 |

`is_empty` and `is_not_empty` compare with an empty string, so they're for text columns, as in QueryBuilder. On other
types of column, PostgreSQL rejects the comparison.


## Values

A value is a string, a number or a boolean. A null value is rejected, as `= NULL` never matches anything in SQL: use
`is_null` or `is_not_null` to compare with `NULL`.

A `%` or `_` in the value of a `LIKE` operator (`begins_with`, `contains`, `ends_with` and their opposites) is escaped,
so it only matches itself, rather than being a wildcard. It's escaped with a backslash, `LIKE`'s escape character, so
the pattern is written as an `E'...'` string, in which `\\` is one backslash:

```php
$json = '{"condition": "AND", "rules": [{"field": "code", "operator": "contains", "value": "50%"}]}';

$sql = $driver->getSql($json);
// $sql is: "code" LIKE E'%50\\%%'
```


## Allowed fields

The JSON comes from the browser, so a user can change a rule's `field` to any column, including ones the page doesn't
offer. Each value and column name is escaped, so this can't inject SQL, but it could let a user find out about a column
they shouldn't see (e.g. with `begins_with` on a `password_hash` column, one character at a time). So the driver needs
the fields the rules may filter on, which are usually the filters you give QueryBuilder, and rejects a rule on any
other field, however deep in the groups it is:

```php
// Throws ExceptionFieldNotAllowed
$json = '{"condition": "AND", "rules": [{"field": "password_hash", "operator": "begins_with", "value": "a"}]}';
$driver->getSql($json);
```

`ExceptionFieldNotAllowed` extends `ExceptionInvalidRules`, so catching that catches both. Catch it on its own if you
want to treat it differently, e.g. to log it, as it may be a user trying to filter on a column they shouldn't.

The fields are matched exactly, including their case, as PostgreSQL matches quoted column names, so `"Name"` and
`"name"` are different columns. `createUsingFields()` throws an `\InvalidArgumentException` if it's given no fields,
so a list built elsewhere (e.g. from config) that turns out to be empty fails where it's given, rather than allowing
every field.

When the rules don't come from a user, e.g. they're built on the server, you can allow any field, by asking for it by
name:

```php
$trustedDriver = new PgSqlDriver($conn, AllowedFields::createAny());

$sql = $trustedDriver->getSql('{"condition": "AND", "rules": [{"field": "status", "operator": "equal", "value": 1}]}');
// $sql is: "status" = 1
```
