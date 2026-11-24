<?php

/*
 * Tests Operator: that each case has QueryBuilder's name for it, and the right SQL operator.
 */

use Programster\QueryBuilderPgsqlDriver\Operator;
use Programster\QueryBuilderPgsqlDriver\Testing\SmokeTest;

/** @var SmokeTest $smoke */

$smoke->run("operator: each case has QueryBuilder's name and the right SQL operator", function (): void {
    $expected = [
        'equal' => '=',
        'not_equal' => '!=',
        'in' => 'IN',
        'not_in' => 'NOT IN',
        'less' => '<',
        'less_or_equal' => '<=',
        'greater' => '>',
        'greater_or_equal' => '>=',
        'between' => 'BETWEEN',
        'not_between' => 'NOT BETWEEN',
        'begins_with' => 'LIKE',
        'not_begins_with' => 'NOT LIKE',
        'contains' => 'LIKE',
        'not_contains' => 'NOT LIKE',
        'ends_with' => 'LIKE',
        'not_ends_with' => 'NOT LIKE',
        'is_empty' => 'IS NULL',
        'is_not_empty' => 'IS NOT NULL',
        'is_null' => 'IS NULL',
        'is_not_null' => 'IS NOT NULL',
    ];

    $actual = [];

    foreach (Operator::cases() as $operator)
    {
        $actual[$operator->value] = $operator->getSqlOperator();
    }

    SmokeTest::assertSame($expected, $actual, "the operators");
});
