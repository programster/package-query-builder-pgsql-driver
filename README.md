Query Builder PostgreSQL Driver
===============================

A PostgreSQL driver for the [query builder parser PHP package](https://github.com/programster/package-query-builder-parser).
This will result in the parser converting the JSON output into SQL statements appropriate for your PostgreSQL database.

## Example Usage

```php
use Programster\QueryBuilderPgsqlDriver\PgSqlDriver;
use Programster\QueryBuilderParser\Parser;

$pgsqlDriver = new PgSqlDriver($conn); // $conn being return from pg_connect()
$parser = new Parser($pgsqlDriver);

// outputs something like: "name" = 'bob' AND "username" LIKE '%yolo%'
$whereCondition = $parser->getSql($jsonString); 

// outputs something like: SELECT * FROM "users" WHERE "name" = 'bob' AND "username" LIKE '%yolo%';
$selectStatement = $parser->getSelectStatement("users", $jsonString); 
```