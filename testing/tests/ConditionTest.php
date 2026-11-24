<?php

/*
 * Tests Condition on its own: that it gives a rule's SQL, and rejects a rule it can't turn into SQL when it's created,
 * rather than when it's turned into a string. The SQL for every operator is tested in PgSqlDriverTest.php.
 */

use Programster\QueryBuilderPgsqlDriver\AllowedFields;
use Programster\QueryBuilderPgsqlDriver\Condition;
use Programster\QueryBuilderPgsqlDriver\ExceptionFieldNotAllowed;
use Programster\QueryBuilderPgsqlDriver\ExceptionInvalidRules;
use Programster\QueryBuilderPgsqlDriver\ExceptionUnrecognizedOperator;
use Programster\QueryBuilderPgsqlDriver\Operator;
use Programster\QueryBuilderPgsqlDriver\Testing\Fixtures\QueryBuilderJson;
use Programster\QueryBuilderPgsqlDriver\Testing\SmokeTest;
use Programster\QueryBuilderPgsqlDriver\Testing\TestDatabase;

/** @var SmokeTest $smoke */

$conn = TestDatabase::getConnection()->getResource();
$fields = AllowedFields::createUsingFields('age', 'price', 'is_admin');

$smoke->run("condition: gives the rule's SQL", function () use ($conn, $fields): void {
    $condition = new Condition(QueryBuilderJson::createRule('age', Operator::GREATER, 3), $conn, $fields);
    SmokeTest::assertSame('"age" > 3', (string)$condition, "the SQL");
});

$smoke->run("condition: only needs a field, operator and value", function () use ($conn, $fields): void {
    $condition = new Condition(['field' => 'age', 'operator' => 'less', 'value' => 3], $conn, $fields);
    SmokeTest::assertSame('"age" < 3', (string)$condition, "the SQL");
});

$smoke->run("condition: doesn't need a value for is_null", function () use ($conn, $fields): void {
    $condition = new Condition(['field' => 'age', 'operator' => 'is_null'], $conn, $fields);
    SmokeTest::assertSame('"age" IS NULL', (string)$condition, "the SQL");
});

$smoke->run("condition: escapes a float with full precision", function () use ($conn, $fields): void {
    $condition = new Condition(QueryBuilderJson::createRule('price', Operator::EQUAL, 0.1 + 0.2), $conn, $fields);
    SmokeTest::assertSame('"price" = 0.30000000000000004', (string)$condition, "the SQL");
});

$smoke->run("condition: escapes a boolean", function () use ($conn, $fields): void {
    $condition = new Condition(QueryBuilderJson::createRule('is_admin', Operator::EQUAL, true), $conn, $fields);
    SmokeTest::assertSame('"is_admin" = TRUE', (string)$condition, "the SQL");
});

$smoke->run("condition: an unknown operator is rejected on creation", function () use ($conn, $fields): void {
    $createCondition = function () use ($conn, $fields): void {
        new Condition(['field' => 'age', 'operator' => 'like', 'value' => 3], $conn, $fields);
    };

    SmokeTest::expectException(ExceptionUnrecognizedOperator::class, $createCondition, 'Unrecognised operator: like');
});

$smoke->run("condition: an invalid value is rejected on creation", function () use ($conn, $fields): void {
    $createCondition = function () use ($conn, $fields): void {
        new Condition(QueryBuilderJson::createRule('age', Operator::BETWEEN, [1]), $conn, $fields);
    };

    SmokeTest::expectException(ExceptionInvalidRules::class, $createCondition, 'The "between" rule on "age"');
});

$smoke->run("condition: a missing field is rejected on creation", function () use ($conn, $fields): void {
    $createCondition = function () use ($conn, $fields): void {
        new Condition(['operator' => 'is_null'], $conn, $fields);
    };

    SmokeTest::expectException(ExceptionInvalidRules::class, $createCondition, '"field"');
});

$smoke->run("condition: a field that isn't allowed is rejected on creation", function () use ($conn, $fields): void {
    $createCondition = function () use ($conn, $fields): void {
        new Condition(['field' => 'password_hash', 'operator' => 'is_null'], $conn, $fields);
    };

    SmokeTest::expectException(ExceptionFieldNotAllowed::class, $createCondition, '"password_hash"');
});
