<?php

/*
 * Tests PgSqlDriver: the SQL it gives for every operator, that the SQL runs on PostgreSQL and matches the right rows,
 * and that every way of giving it rules it can't turn into SQL is rejected.
 */

use Programster\QueryBuilderParser\Parser;
use Programster\QueryBuilderPgsqlDriver\AllowedFields;
use Programster\QueryBuilderPgsqlDriver\Conjunction;
use Programster\QueryBuilderPgsqlDriver\ExceptionFieldNotAllowed;
use Programster\QueryBuilderPgsqlDriver\ExceptionInvalidConjunction;
use Programster\QueryBuilderPgsqlDriver\ExceptionInvalidRules;
use Programster\QueryBuilderPgsqlDriver\ExceptionUnrecognizedOperator;
use Programster\QueryBuilderPgsqlDriver\Operator;
use Programster\QueryBuilderPgsqlDriver\PgSqlDriver;
use Programster\QueryBuilderPgsqlDriver\Testing\Fixtures\QueryBuilderJson;
use Programster\QueryBuilderPgsqlDriver\Testing\SmokeTest;
use Programster\QueryBuilderPgsqlDriver\Testing\TestDatabase;

/** @var SmokeTest $smoke */

$db = TestDatabase::getConnection();
$allowedFields = AllowedFields::createUsingFields('name', 'age', 'nick', 'my "field"', 'a', 'b');
$driver = new PgSqlDriver($db->getResource(), $allowedFields);

# A temporary table, which only this connection can see, and which is dropped when it closes.
$db->query("DROP TABLE IF EXISTS pg_temp.people");
$db->query("CREATE TEMPORARY TABLE people (name text, age integer, nick text)");

$db->query(
    "INSERT INTO people (name, age, nick) VALUES ('alice', 30, 'Al'), ('bob', 17, ''), ('carol', 70, NULL), "
    . "('dave', 45, '50%_off'), ('eve', 30, 'O''Brien'), ('frank', 50, 'C:\\temp')"
);

# Get the names of the people that match the rules, sorted and separated by commas.
$fetchMatchingNames = function (string $json) use ($db, $driver): string {
    $result = $db->query("SELECT name FROM (" . rtrim($driver->getSelectStatement("people", $json), ';') . ") AS m");
    $names = pg_fetch_all_columns($result);
    sort($names);
    return implode(",", $names);
};

# Each operator, the value it's given, the SQL it should give, and who it should match.
$operatorCases = [
    [Operator::EQUAL, 'name', 'bob', "\"name\" = 'bob'", 'bob'],
    [Operator::NOT_EQUAL, 'name', 'bob', "\"name\" != 'bob'", 'alice,carol,dave,eve,frank'],
    [Operator::IN, 'age', [30, 45], '"age" IN (30,45)', 'alice,dave,eve'],
    [Operator::NOT_IN, 'age', [30], '"age" NOT IN (30)', 'bob,carol,dave,frank'],
    [Operator::LESS, 'age', 30, '"age" < 30', 'bob'],
    [Operator::LESS_OR_EQUAL, 'age', 30, '"age" <= 30', 'alice,bob,eve'],
    [Operator::GREATER, 'age', 45, '"age" > 45', 'carol,frank'],
    [Operator::GREATER_OR_EQUAL, 'age', 45, '"age" >= 45', 'carol,dave,frank'],
    [Operator::BETWEEN, 'age', [17, 30], '"age" BETWEEN 17 AND 30', 'alice,bob,eve'],
    [Operator::NOT_BETWEEN, 'age', [17, 30], '"age" NOT BETWEEN 17 AND 30', 'carol,dave,frank'],
    [Operator::BEGINS_WITH, 'name', 'a', "\"name\" LIKE 'a%'", 'alice'],
    [Operator::NOT_BEGINS_WITH, 'name', 'a', "\"name\" NOT LIKE 'a%'", 'bob,carol,dave,eve,frank'],
    [Operator::CONTAINS, 'name', 'o', "\"name\" LIKE '%o%'", 'bob,carol'],
    [Operator::NOT_CONTAINS, 'name', 'o', "\"name\" NOT LIKE '%o%'", 'alice,dave,eve,frank'],
    [Operator::ENDS_WITH, 'name', 'e', "\"name\" LIKE '%e'", 'alice,dave,eve'],
    [Operator::NOT_ENDS_WITH, 'name', 'e', "\"name\" NOT LIKE '%e'", 'bob,carol,frank'],
    [Operator::IS_EMPTY, 'nick', null, "(\"nick\" IS NULL OR \"nick\" = '')", 'bob,carol'],
    [Operator::IS_NOT_EMPTY, 'nick', null, "(\"nick\" IS NOT NULL AND \"nick\" != '')", 'alice,dave,eve,frank'],
    [Operator::IS_NULL, 'nick', null, '"nick" IS NULL', 'carol'],
    [Operator::IS_NOT_NULL, 'nick', null, '"nick" IS NOT NULL', 'alice,bob,dave,eve,frank'],
];

foreach ($operatorCases as [$operator, $field, $value, $expectedSql, $expectedNames])
{
    $smoke->run("driver: {$operator->value} gives the right SQL, which matches the right rows", function () use (
        $driver,
        $fetchMatchingNames,
        $operator,
        $field,
        $value,
        $expectedSql,
        $expectedNames
    ): void {
        $rule = QueryBuilderJson::createRule($field, $operator, $value);
        $json = QueryBuilderJson::encode(QueryBuilderJson::createGroup(Conjunction::AND, $rule));
        SmokeTest::assertSame($expectedSql, $driver->getSql($json), "the SQL");
        SmokeTest::assertSame($expectedNames, $fetchMatchingNames($json), "the matching names");
    });
}

$smoke->run("driver: every operator is tested", function () use ($operatorCases): void {
    $testedOperators = array_column($operatorCases, 0);
    SmokeTest::assertSame(count(Operator::cases()), count(array_unique($testedOperators, SORT_REGULAR)), "operators");
});

# A % or _ in a LIKE operator's value only matches itself, rather than being a wildcard, and quotes and backslashes
# are escaped.
$patternCases = [
    [Operator::CONTAINS, '%', 'dave'],
    [Operator::CONTAINS, '_', 'dave'],
    [Operator::BEGINS_WITH, '50%', 'dave'],
    [Operator::CONTAINS, '\\', 'frank'],
    [Operator::ENDS_WITH, ':\\temp', 'frank'],
    [Operator::BEGINS_WITH, "O'B", 'eve'],
    [Operator::NOT_CONTAINS, '%', 'alice,bob,eve,frank'],
];

foreach ($patternCases as [$operator, $value, $expectedNames])
{
    $smoke->run("driver: {$operator->value} {$value} only matches it literally", function () use (
        $fetchMatchingNames,
        $operator,
        $value,
        $expectedNames
    ): void {
        $rule = QueryBuilderJson::createRule('nick', $operator, $value);
        $json = QueryBuilderJson::encode(QueryBuilderJson::createGroup(Conjunction::AND, $rule));
        SmokeTest::assertSame($expectedNames, $fetchMatchingNames($json), "the matching names");
    });
}

$smoke->run("driver: a quote in a value is escaped", function () use ($driver, $fetchMatchingNames): void {
    $rule = QueryBuilderJson::createRule('nick', Operator::EQUAL, "O'Brien");
    $json = QueryBuilderJson::encode(QueryBuilderJson::createGroup(Conjunction::AND, $rule));
    SmokeTest::assertSame("\"nick\" = 'O''Brien'", $driver->getSql($json), "the SQL");
    SmokeTest::assertSame('eve', $fetchMatchingNames($json), "the matching names");
});

$smoke->run("driver: a quote in a field is escaped", function () use ($driver): void {
    $rule = QueryBuilderJson::createRule('my "field"', Operator::IS_NULL);
    $json = QueryBuilderJson::encode(QueryBuilderJson::createGroup(Conjunction::AND, $rule));
    SmokeTest::assertSame('"my ""field""" IS NULL', $driver->getSql($json), "the SQL");
});

$smoke->run("driver: getSelectStatement() selects from the escaped table", function () use ($driver): void {
    $rule = QueryBuilderJson::createRule('name', Operator::EQUAL, 'bob');
    $json = QueryBuilderJson::encode(QueryBuilderJson::createGroup(Conjunction::AND, $rule));
    $sql = $driver->getSelectStatement('my "table"', $json);
    SmokeTest::assertSame("SELECT * FROM \"my \"\"table\"\"\" WHERE \"name\" = 'bob';", $sql, "the SQL");
});

$smoke->run("driver: the rules of a group are joined by its conjunction", function () use ($driver): void {
    $name = QueryBuilderJson::createRule('name', Operator::EQUAL, 'bob');
    $age = QueryBuilderJson::createRule('age', Operator::BETWEEN, [5, 6]);
    $andJson = QueryBuilderJson::encode(QueryBuilderJson::createGroup(Conjunction::AND, $name, $age));
    $orJson = QueryBuilderJson::encode(QueryBuilderJson::createGroup(Conjunction::OR, $name, $age));
    SmokeTest::assertSame("\"name\" = 'bob' AND \"age\" BETWEEN 5 AND 6", $driver->getSql($andJson), "AND");
    SmokeTest::assertSame("\"name\" = 'bob' OR \"age\" BETWEEN 5 AND 6", $driver->getSql($orJson), "OR");
});

# Without the brackets, age < 20 AND (name = bob OR name = carol) would be read as (age < 20 AND name = bob) OR
# name = carol, which also matches carol, who is 70.
$smoke->run("driver: a group in a group is bracketed", function () use ($driver, $fetchMatchingNames): void {
    $bobOrCarol = QueryBuilderJson::createGroup(
        Conjunction::OR,
        QueryBuilderJson::createRule('name', Operator::EQUAL, 'bob'),
        QueryBuilderJson::createRule('name', Operator::EQUAL, 'carol')
    );

    $young = QueryBuilderJson::createRule('age', Operator::LESS, 20);
    $json = QueryBuilderJson::encode(QueryBuilderJson::createGroup(Conjunction::AND, $young, $bobOrCarol));
    $expectedSql = "\"age\" < 20 AND (\"name\" = 'bob' OR \"name\" = 'carol')";
    SmokeTest::assertSame($expectedSql, $driver->getSql($json), "the SQL");
    SmokeTest::assertSame('bob', $fetchMatchingNames($json), "the matching names");
});

$smoke->run("driver: rules and groups keep their order", function () use ($driver): void {
    $group = QueryBuilderJson::createGroup(Conjunction::OR, QueryBuilderJson::createRule('a', Operator::IS_NULL));
    $rule = QueryBuilderJson::createRule('b', Operator::IS_NULL);
    $json = QueryBuilderJson::encode(QueryBuilderJson::createGroup(Conjunction::AND, $group, $rule));
    SmokeTest::assertSame('("a" IS NULL) AND "b" IS NULL', $driver->getSql($json), "the SQL");
});

$smoke->run("driver: a not group matches the rows it would not", function () use ($driver, $fetchMatchingNames): void {
    $old = QueryBuilderJson::createRule('age', Operator::GREATER, 40);
    $json = QueryBuilderJson::encode([...QueryBuilderJson::createGroup(Conjunction::AND, $old), 'not' => true]);
    SmokeTest::assertSame('NOT ("age" > 40)', $driver->getSql($json), "the SQL");
    SmokeTest::assertSame('alice,bob,eve', $fetchMatchingNames($json), "the matching names");
});

$smoke->run("driver: a group with not set to false matches as normal", function () use ($driver): void {
    $old = QueryBuilderJson::createRule('age', Operator::GREATER, 40);
    $json = QueryBuilderJson::encode([...QueryBuilderJson::createGroup(Conjunction::AND, $old), 'not' => false]);
    SmokeTest::assertSame('"age" > 40', $driver->getSql($json), "the SQL");
});

$smoke->run("driver: rules with no valid key are accepted", function () use ($driver): void {
    $json = '{"condition": "AND", "rules": [{"field": "name", "operator": "is_null"}]}';
    SmokeTest::assertSame('"name" IS NULL', $driver->getSql($json), "the SQL");
});

$smoke->run("driver: it works through the parser", function () use ($driver): void {
    $parser = new Parser($driver);
    $rule = QueryBuilderJson::createRule('name', Operator::EQUAL, 'bob');
    $json = QueryBuilderJson::encode(QueryBuilderJson::createGroup(Conjunction::AND, $rule));
    SmokeTest::assertSame("\"name\" = 'bob'", $parser->getSql($json), "getSql()");
    $expectedSelect = "SELECT * FROM \"users\" WHERE \"name\" = 'bob';";
    SmokeTest::assertSame($expectedSelect, $parser->getSelectStatement("users", $json), "getSelectStatement()");
});

$smoke->run("driver: a rule on a field that isn't allowed is rejected", function () use ($driver): void {
    $rule = QueryBuilderJson::createRule('password_hash', Operator::BEGINS_WITH, 'a');
    $json = QueryBuilderJson::encode(QueryBuilderJson::createGroup(Conjunction::AND, $rule));

    $getSql = function () use ($driver, $json): void {
        $driver->getSql($json);
    };

    $getSelectStatement = function () use ($driver, $json): void {
        $driver->getSelectStatement('people', $json);
    };

    SmokeTest::expectException(ExceptionFieldNotAllowed::class, $getSql, 'Filtering on "password_hash" isn\'t allowed');
    SmokeTest::expectException(ExceptionFieldNotAllowed::class, $getSelectStatement, '"password_hash"');
});

$smoke->run("driver: a field that isn't allowed is rejected in a group in a group", function () use ($driver): void {
    $passwordRule = QueryBuilderJson::createRule('password_hash', Operator::IS_NULL);

    $nested = QueryBuilderJson::createGroup(
        Conjunction::OR,
        QueryBuilderJson::createRule('name', Operator::EQUAL, 'bob'),
        QueryBuilderJson::createGroup(Conjunction::AND, $passwordRule)
    );

    $json = QueryBuilderJson::encode(QueryBuilderJson::createGroup(Conjunction::AND, $nested));

    $getSql = function () use ($driver, $json): void {
        $driver->getSql($json);
    };

    SmokeTest::expectException(ExceptionFieldNotAllowed::class, $getSql, '"password_hash"');
});

$smoke->run("driver: a field is only allowed in the same case", function () use ($driver): void {
    $rule = QueryBuilderJson::createRule('Name', Operator::EQUAL, 'bob');
    $json = QueryBuilderJson::encode(QueryBuilderJson::createGroup(Conjunction::AND, $rule));

    $getSql = function () use ($driver, $json): void {
        $driver->getSql($json);
    };

    SmokeTest::expectException(ExceptionFieldNotAllowed::class, $getSql, '"Name"');
});

$smoke->run("driver: a field that isn't allowed can be caught as invalid rules", function () use ($driver): void {
    $rule = QueryBuilderJson::createRule('password_hash', Operator::IS_NULL);
    $json = QueryBuilderJson::encode(QueryBuilderJson::createGroup(Conjunction::AND, $rule));

    $getSql = function () use ($driver, $json): void {
        $driver->getSql($json);
    };

    SmokeTest::expectException(ExceptionInvalidRules::class, $getSql, '"password_hash"');
});

$smoke->run("driver: any field can be allowed", function () use ($db): void {
    $driver = new PgSqlDriver($db->getResource(), AllowedFields::createAny());
    $rule = QueryBuilderJson::createRule('password_hash', Operator::IS_NULL);
    $json = QueryBuilderJson::encode(QueryBuilderJson::createGroup(Conjunction::AND, $rule));
    SmokeTest::assertSame('"password_hash" IS NULL', $driver->getSql($json), "the SQL");
});

# Each JSON the driver should refuse, the exception it should throw, and text its message should contain.
$rule = '{"field": "name", "operator": "equal", "value": "bob"}';

$invalidJsonCases = [
    'JSON that is invalid' => [
        '{"condition": ',
        \JsonException::class,
        '',
    ],
    'JSON that is not an object' => [
        '5',
        ExceptionInvalidRules::class,
        'must be an object',
    ],
    'rules marked as invalid' => [
        '{"condition": "AND", "rules": [' . $rule . '], "valid": false}',
        ExceptionInvalidRules::class,
        'marked as invalid',
    ],
    'a valid that is not true' => [
        '{"condition": "AND", "rules": [' . $rule . '], "valid": "yes"}',
        ExceptionInvalidRules::class,
        'marked as invalid',
    ],
    'a group with no condition' => [
        '{"rules": [' . $rule . ']}',
        ExceptionInvalidRules::class,
        '"condition"',
    ],
    'a condition that is not a string' => [
        '{"condition": 1, "rules": [' . $rule . ']}',
        ExceptionInvalidRules::class,
        '"condition"',
    ],
    'a condition of XOR' => [
        '{"condition": "XOR", "rules": [' . $rule . ']}',
        ExceptionInvalidConjunction::class,
        'XOR',
    ],
    'a condition in lower case' => [
        '{"condition": "and", "rules": [' . $rule . ']}',
        ExceptionInvalidConjunction::class,
        'and',
    ],
    'a group with no rules key' => [
        '{"condition": "AND"}',
        ExceptionInvalidRules::class,
        '"rules"',
    ],
    'a group with no rules' => [
        '{"condition": "AND", "rules": []}',
        ExceptionInvalidRules::class,
        'non-empty list',
    ],
    'rules that are an object' => [
        '{"condition": "AND", "rules": {"a": ' . $rule . '}}',
        ExceptionInvalidRules::class,
        'non-empty list',
    ],
    'a group in a group with no rules' => [
        '{"condition": "AND", "rules": [{"condition": "OR", "rules": []}]}',
        ExceptionInvalidRules::class,
        'non-empty list',
    ],
    'a not that is not a boolean' => [
        '{"condition": "AND", "not": "true", "rules": [' . $rule . ']}',
        ExceptionInvalidRules::class,
        '"not"',
    ],
    'a rule that is not an object' => [
        '{"condition": "AND", "rules": [5]}',
        ExceptionInvalidRules::class,
        'rule or a group',
    ],
    'a rule with no field' => [
        '{"condition": "AND", "rules": [{"operator": "is_null"}]}',
        ExceptionInvalidRules::class,
        '"field"',
    ],
    'a rule with an empty field' => [
        '{"condition": "AND", "rules": [{"field": "", "operator": "is_null"}]}',
        ExceptionInvalidRules::class,
        '"field"',
    ],
    'a rule with a field that is a number' => [
        '{"condition": "AND", "rules": [{"field": 5, "operator": "is_null"}]}',
        ExceptionInvalidRules::class,
        '"field"',
    ],
    'a rule with no operator' => [
        '{"condition": "AND", "rules": [{"field": "name"}]}',
        ExceptionInvalidRules::class,
        '"operator"',
    ],
    'a rule with an unknown operator' => [
        '{"condition": "AND", "rules": [{"field": "name", "operator": "like"}]}',
        ExceptionUnrecognizedOperator::class,
        'like',
    ],
    'an unknown operator in a group in a group' => [
        '{"condition": "AND", "rules": [{"condition": "OR", "rules": [{"field": "a", "operator": "x"}]}]}',
        ExceptionUnrecognizedOperator::class,
        'x',
    ],
];

foreach ($invalidJsonCases as $description => [$json, $exceptionClass, $messageContains])
{
    $smoke->run("driver: {$description} is rejected", function () use (
        $driver,
        $json,
        $exceptionClass,
        $messageContains
    ): void {
        $getSql = function () use ($driver, $json): void {
            $driver->getSql($json);
        };

        $getSelectStatement = function () use ($driver, $json): void {
            $driver->getSelectStatement('people', $json);
        };

        SmokeTest::expectException($exceptionClass, $getSql, $messageContains);
        SmokeTest::expectException($exceptionClass, $getSelectStatement, $messageContains);
    });
}

# Each operator and value that can't be compared, and text the message should contain.
$invalidValueCases = [
    [Operator::EQUAL, null, 'Use is_null'],
    [Operator::NOT_EQUAL, null, 'Use is_null'],
    [Operator::GREATER, null, 'Use is_null'],
    [Operator::EQUAL, [1], 'strings, numbers or booleans'],
    [Operator::LESS, ['a' => 1], 'strings, numbers or booleans'],
    [Operator::IN, [], 'non-empty list'],
    [Operator::IN, '1,2', 'non-empty list'],
    [Operator::IN, ['a' => 1], 'non-empty list'],
    [Operator::IN, [1, null], 'Use is_null'],
    [Operator::NOT_IN, [[1]], 'strings, numbers or booleans'],
    [Operator::BETWEEN, [1], 'exactly two'],
    [Operator::BETWEEN, [1, 2, 3], 'exactly two'],
    [Operator::BETWEEN, 5, 'exactly two'],
    [Operator::NOT_BETWEEN, [null, 2], 'Use is_null'],
    [Operator::CONTAINS, null, 'string or a number'],
    [Operator::BEGINS_WITH, ['a'], 'string or a number'],
    [Operator::ENDS_WITH, true, 'string or a number'],
];

foreach ($invalidValueCases as [$operator, $value, $messageContains])
{
    $describedValue = json_encode($value, JSON_THROW_ON_ERROR);

    $smoke->run("driver: {$operator->value} with a value of {$describedValue} is rejected", function () use (
        $driver,
        $operator,
        $value,
        $messageContains
    ): void {
        $rule = QueryBuilderJson::createRule('age', $operator, $value);
        $json = QueryBuilderJson::encode(QueryBuilderJson::createGroup(Conjunction::AND, $rule));

        $getSql = function () use ($driver, $json): void {
            $driver->getSql($json);
        };

        SmokeTest::expectException(ExceptionInvalidRules::class, $getSql, $messageContains);
    });
}
