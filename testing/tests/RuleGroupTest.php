<?php

/*
 * Tests RuleGroup on its own: that it gives a group's SQL, and rejects a group it can't turn into SQL when it's
 * created. The rest of the ways a group can be invalid are tested in PgSqlDriverTest.php.
 */

use Programster\QueryBuilderPgsqlDriver\AllowedFields;
use Programster\QueryBuilderPgsqlDriver\Conjunction;
use Programster\QueryBuilderPgsqlDriver\ExceptionFieldNotAllowed;
use Programster\QueryBuilderPgsqlDriver\ExceptionInvalidConjunction;
use Programster\QueryBuilderPgsqlDriver\ExceptionInvalidRules;
use Programster\QueryBuilderPgsqlDriver\ExceptionUnrecognizedOperator;
use Programster\QueryBuilderPgsqlDriver\Operator;
use Programster\QueryBuilderPgsqlDriver\RuleGroup;
use Programster\QueryBuilderPgsqlDriver\Testing\Fixtures\QueryBuilderJson;
use Programster\QueryBuilderPgsqlDriver\Testing\SmokeTest;
use Programster\QueryBuilderPgsqlDriver\Testing\TestDatabase;

/** @var SmokeTest $smoke */

$conn = TestDatabase::getConnection()->getResource();
$fields = AllowedFields::createUsingFields('a', 'b', 'c');

$smoke->run("rule group: gives the group's SQL", function () use ($conn, $fields): void {
    $group = QueryBuilderJson::createGroup(
        Conjunction::OR,
        QueryBuilderJson::createRule('a', Operator::EQUAL, 1),
        QueryBuilderJson::createGroup(
            Conjunction::AND,
            QueryBuilderJson::createRule('b', Operator::EQUAL, 2),
            QueryBuilderJson::createRule('c', Operator::EQUAL, 3)
        )
    );

    SmokeTest::assertSame('"a" = 1 OR ("b" = 2 AND "c" = 3)', (string)new RuleGroup($group, $conn, $fields), "the SQL");
});

$smoke->run("rule group: a not group is negated", function () use ($conn, $fields): void {
    $group = QueryBuilderJson::createGroup(Conjunction::AND, QueryBuilderJson::createRule('a', Operator::IS_NULL));
    $group['not'] = true;
    SmokeTest::assertSame('NOT ("a" IS NULL)', (string)new RuleGroup($group, $conn, $fields), "the SQL");
});

$smoke->run("rule group: an invalid conjunction is rejected on creation", function () use ($conn, $fields): void {
    $createGroup = function () use ($conn, $fields): void {
        new RuleGroup(['condition' => 'XOR', 'rules' => [['field' => 'a', 'operator' => 'is_null']]], $conn, $fields);
    };

    SmokeTest::expectException(ExceptionInvalidConjunction::class, $createGroup, "Invalid conjunction provided: XOR");
});

$smoke->run("rule group: an empty group is rejected on creation", function () use ($conn, $fields): void {
    $createGroup = function () use ($conn, $fields): void {
        new RuleGroup(QueryBuilderJson::createGroup(Conjunction::AND), $conn, $fields);
    };

    SmokeTest::expectException(ExceptionInvalidRules::class, $createGroup, 'non-empty list');
});

$smoke->run("rule group: an invalid rule in it is rejected on creation", function () use ($conn, $fields): void {
    $createGroup = function () use ($conn, $fields): void {
        new RuleGroup(['condition' => 'AND', 'rules' => [['field' => 'a', 'operator' => 'nope']]], $conn, $fields);
    };

    SmokeTest::expectException(ExceptionUnrecognizedOperator::class, $createGroup, 'nope');
});

$smoke->run("rule group: a field that isn't allowed is rejected on creation", function () use ($conn, $fields): void {
    $createGroup = function () use ($conn, $fields): void {
        $group = QueryBuilderJson::createGroup(Conjunction::AND, QueryBuilderJson::createRule('d', Operator::IS_NULL));
        new RuleGroup(QueryBuilderJson::createGroup(Conjunction::OR, $group), $conn, $fields);
    };

    SmokeTest::expectException(ExceptionFieldNotAllowed::class, $createGroup, '"d"');
});
