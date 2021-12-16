<?php

namespace Programster\QueryBuilderPgsqlDriver\Testing\Tests;


use Programster\QueryBuilderPgsqlDriver\Testing\AbstractTest;
use Programster\QueryBuilderPgsqlDriver\Testing\TestHelperLib;
use Programster\QueryBuilderPgsqlDriver\PgSqlDriver;
use Programster\QueryBuilderParser\Parser;


class TestBasicExample extends AbstractTest
{
    public function getDescription(): string
    {
        return "An example test.";
    }


    public function run()
    {
        $this->m_passed = false;

        $jsonString = '{
            "condition": "AND",
            "rules": [
                {
                    "id": "name",
                    "field": "name",
                    "type": "string",
                    "input": "text",
                    "operator": "equal",
                    "value": "bob"
                },
                {
                    "id": "age",
                    "field": "age",
                    "type": "integer",
                    "input": "number",
                    "operator": "between",
                    "value": [
                        5,
                        6
                    ]
                }
            ],
            "valid": true
        }';

        $pgsqlDriver = new PgSqlDriver(TestHelperLib::getDb()->getResource());
        $parser = new Parser($pgsqlDriver);

        $whereCondition = $parser->getSql($jsonString);
        $selectStatement = $parser->getSelectStatement("users", $jsonString);

        if ($selectStatement !== 'SELECT * FROM "users" WHERE "name" = \'bob\' AND "age" BETWEEN 5 AND 6;')
        {
            $this->m_passed = false;
            $this->m_errorMessages[] = "Generated an incorrect select statement: {$selectStatement}";
        }
        else if ($whereCondition !== '"name" = \'bob\' AND "age" BETWEEN 5 AND 6')
        {
            $this->m_passed = false;
            $this->m_errorMessages[] = "Generated an incorrect where condition: {$whereCondition}";
        }
        else
        {
            $this->m_passed = true;
        }
    }
}
