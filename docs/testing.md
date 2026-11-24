# Testing

These are the tests for this package itself. They're only in the repository, not in the package Composer installs.

The package is tested with smoke tests (in `testing/`), rather than PHPUnit, so there's no test framework to install or
keep working on every version of PHP. They call every public method, check the SQL for every operator, run that SQL
against a temporary table to check it matches the right rows, and check that every way of giving the driver rules it
can't turn into SQL is rejected. A test fails if it throws, fails an `assert()`, or raises any warning, notice or
deprecation.

The driver needs a database connection to escape values for, so the tests need a PostgreSQL server. They only create a
temporary table, which is dropped when the connection closes, so they never change anything else in the database.


## Running them on every supported version of PHP

This lints every file and runs the tests on PHP 8.2, 8.3, 8.4, 8.5 and 8.6, each in its own Docker container, against
PostgreSQL 19 in another, then runs PHPStan once, on the lowest version of PHP:

```bash
composer install
testing/run-all-php-versions.sh
```

- To test only some versions, pass them, e.g. `testing/run-all-php-versions.sh 8.2 8.6`.
- To rebuild the Docker images first, e.g. to pick up the latest patch releases, pass `--rebuild`.


## Running them on every supported version of PostgreSQL

This runs the tests on PHP 8.6 against PostgreSQL 15, 16, 17, 18 and 19, each in its own Docker container:

```bash
composer install
testing/run-all-postgresql-versions.sh
```

To test only some versions, pass them, e.g. `testing/run-all-postgresql-versions.sh 15 19`.

Both scripts put the containers on their own Docker network, which has no access to the internet or the host, and
remove the PostgreSQL containers (which keep their data in memory) and the network afterwards.


## Running them on your own PHP

Copy `testing/settings.php.tmpl` to `testing/settings.php` (which git ignores), fill in the connection details of a
PostgreSQL database, and run:

```bash
php -d zend.assertions=1 testing/main.php
```

Each setting can also be given as an environment variable of the same name with a `TEST_` prefix, e.g. `TEST_DB_PORT`,
which takes priority over `settings.php`.

`zend.assertions` must be 1, or the `assert()` checks would silently do nothing, so the tests refuse to run without it.
To run only the tests whose name contains some text, set `PGSQL_DRIVER_TEST_ONLY`, e.g.
`PGSQL_DRIVER_TEST_ONLY=between php -d zend.assertions=1 testing/main.php`.


## PHPStan

```bash
vendor/bin/phpstan
```

It runs at the highest level, 10, set in `phpstan.neon.dist`, and analyses the code as PHP 8.2 to 8.6, whichever version
of PHP runs it.


## The documentation's examples

`testing/tests/DocsTest.php` runs the PHP examples in the README and every page in `docs/`, so the documentation can't
drift from what the code does. Each document's examples run in order, as one script, in a separate PHP process, with a
`use` statement for every class in the package, and `$conn` set to a connection to the test database. A line such as
`// $sql is: "age" > 3` checks that `$sql` is exactly that, and an example whose first line is
`// Throws SomeException` must throw that exception. See `testing/DocsExampleRunner.php`.


## GitHub Actions

`.github/workflows/tests.yml` runs both scripts for pushes to `main`, every tag, and pull requests into `main`, and can
be run by hand on any branch with "Run workflow" on the repository's Actions tab. The README's "Tests" badge shows the
latest result on `main`. Each run also lists its results and any failed tests as annotations on the run's summary page
(written by `testing/github-annotations.sh`), which can be read without logging in to GitHub.


## Adding a test

Each class has a file of tests in `testing/tests/`, e.g. `PgSqlDriverTest.php`, and `testing/Fixtures/QueryBuilderJson.php`
builds rules in the shape QueryBuilder gives them. A test is a named callback:

```php
use Programster\QueryBuilderPgsqlDriver\Testing\Fixtures\QueryBuilderJson;
use Programster\QueryBuilderPgsqlDriver\Testing\SmokeTest;

$smoke = new SmokeTest();

$smoke->run("condition: a between rule needs two values", function () use ($conn): void {
    $createCondition = function () use ($conn): void {
        new Condition(QueryBuilderJson::createRule('age', Operator::BETWEEN, [1]), $conn, AllowedFields::createAny());
    };

    SmokeTest::expectException(ExceptionInvalidRules::class, $createCondition, 'exactly two');
});

assert(count($smoke->getFailures()) === 0, implode("\n", $smoke->getFailures()));
```
