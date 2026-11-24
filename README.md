# Query Builder PostgreSQL Driver

[![Tests](https://github.com/programster/package-query-builder-pgsql-driver/actions/workflows/tests.yml/badge.svg)](https://github.com/programster/package-query-builder-pgsql-driver/actions/workflows/tests.yml)

- [What it's for](#what-its-for)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Design principles](#design-principles)
- [Documentation](#documentation)
  - [Rules and operators](docs/rules.md)
  - [Exceptions](docs/exceptions.md)
  - [Testing](docs/testing.md)
  - [Upgrading](docs/upgrading.md)
- [Licence](#licence)


## What it's for

[jQuery QueryBuilder](https://querybuilder.js.org/) lets a user build a filter, such as "name is bob, and age is
less than 18 or more than 65", in the browser, and gives it as JSON. This package is the PostgreSQL driver for the
[query builder parser](https://github.com/programster/package-query-builder-parser), which turns that JSON into the
SQL for a `WHERE` clause, with every value and column name escaped for your database.


## Requirements

- PHP 8.2 to 8.6, with the `pgsql` extension.
- PostgreSQL 15 to 19 (it's tested against each of them).
- A connection to the database, as the values are escaped for it.


## Installation

```bash
composer require programster/query-builder-pgsql-driver
```


## Quick start

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Programster\QueryBuilderParser\Parser;
use Programster\QueryBuilderPgsqlDriver\AllowedFields;
use Programster\QueryBuilderPgsqlDriver\PgSqlDriver;

// $conn is a PgSql\Connection, e.g. from pg_connect(), or pgsql-lib's PgSqlConnection::getResource().
// Only rules on these fields (columns) are allowed. Rules on any other field are rejected.
$parser = new Parser(new PgSqlDriver($conn, AllowedFields::createUsingFields('name', 'age')));

// The JSON from QueryBuilder's getRules(), e.g. sent to the server by the page.
$json = '{
    "condition": "AND",
    "rules": [
        {"id": "name", "field": "name", "type": "string", "operator": "equal", "value": "bob"},
        {
            "condition": "OR",
            "rules": [
                {"id": "age", "field": "age", "type": "integer", "operator": "less", "value": 18},
                {"id": "age", "field": "age", "type": "integer", "operator": "greater", "value": 65}
            ]
        }
    ],
    "valid": true
}';

$where = $parser->getSql($json);
// $where is: "name" = 'bob' AND ("age" < 18 OR "age" > 65)

$select = $parser->getSelectStatement("users", $json);
// $select is: SELECT * FROM "users" WHERE "name" = 'bob' AND ("age" < 18 OR "age" > 65);
```

**Why the fields have to be given.** The values and column names are escaped, so they can't inject SQL, but the JSON
comes from the browser, so a user can change it to filter on any column of the table, including ones the page doesn't
offer (e.g. `password_hash`), and learn about them from which rows match. So the driver only allows rules on the
fields you give it, and throws an `ExceptionFieldNotAllowed` for any other. See
[Rules and operators](docs/rules.md#allowed-fields).


## Design principles

- **Safe by default.** The driver only allows rules on the fields you give it, so a user can't filter on a column the
  page doesn't offer. Allowing any field has to be asked for by name, with `AllowedFields::createAny()`.
- **Every rule is checked before any SQL is made.** A rule the driver can't turn into SQL that does what it says (an
  unknown operator, a `between` without two values, an `equal` with a null value, an empty group, and so on) throws an
  exception naming the rule, rather than giving SQL that fails, or silently matches the wrong rows.
- **Values match what they say.** Values and column names are escaped, a group within a group is bracketed, so `AND`
  and `OR` apply in the order the user built them, and a `%` or `_` in a `contains` (or other `LIKE`) value only
  matches itself, rather than being a wildcard.
- **Small.** It does one job, turning QueryBuilder's JSON into SQL, and leaves running it to you.


## Documentation

The [docs](docs/README.md) cover the [rules and operators](docs/rules.md) the driver understands, the
[exceptions](docs/exceptions.md) it throws, [testing](docs/testing.md) the package, and
[upgrading](docs/upgrading.md) from an earlier version.


## Licence

MIT. See [LICENSE](LICENSE).
