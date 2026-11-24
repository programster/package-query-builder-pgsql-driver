# Exceptions

`getSql()` and `getSelectStatement()` check every rule before making any SQL, and throw one of these if they can't turn
the rules into SQL. Each extends PHP's `\Exception`, and its message names the rule that's wrong.

| Exception                       | Thrown when                                                                 |
|---------------------------------|-----------------------------------------------------------------------------|
| `\JsonException`                | The JSON can't be decoded.                                                  |
| `ExceptionFieldNotAllowed`      | A rule filters on a field that isn't [allowed](rules.md#allowed-fields). It extends `ExceptionInvalidRules`. |
| `ExceptionInvalidRules`         | The rules are marked as invalid, or aren't in the shape QueryBuilder gives, e.g. a rule with no field, a group with no rules, or a value that isn't what the operator needs. |
| `ExceptionInvalidConjunction`   | A group's `condition` isn't `AND` or `OR`.                                  |
| `ExceptionUnrecognizedOperator` | A rule's operator isn't one of [QueryBuilder's](rules.md#operators).        |

E.g. a `between` rule needs exactly two values:

```php
<?php

use Programster\QueryBuilderPgsqlDriver\AllowedFields;
use Programster\QueryBuilderPgsqlDriver\ExceptionInvalidRules;
use Programster\QueryBuilderPgsqlDriver\PgSqlDriver;

$driver = new PgSqlDriver($conn, AllowedFields::createUsingFields('name', 'age'));
$json = '{"condition": "AND", "rules": [{"field": "age", "operator": "between", "value": [18]}]}';

try
{
    $driver->getSql($json);
}
catch (ExceptionInvalidRules $e)
{
    $message = $e->getMessage();
}

// $message is: The "between" rule on "age" needs a value that's a list of exactly two values.
```

And an operator must be one of QueryBuilder's:

```php
// Throws ExceptionUnrecognizedOperator
$driver->getSql('{"condition": "AND", "rules": [{"field": "name", "operator": "like", "value": "bob"}]}');
```

`AllowedFields::createUsingFields()` throws an `\InvalidArgumentException` if it's given no fields, or a field is an
empty string.

`Condition` and `RuleGroup`, which the driver uses to build the SQL, check their rules in their constructors, so they
throw the same exceptions when they're created.
