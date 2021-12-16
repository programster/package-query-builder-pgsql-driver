<?php

/*
 * A driver for the parser that works against a PostgreSql database.
 */

namespace Programster\QueryBuilderPgsqlDriver;

use Exception;
use Programster\QueryBuilderParser\InterfaceParserDriver;


final class PgSqlDriver implements InterfaceParserDriver
{
    private \Pgsql\Connection $m_pgsqlConnection;


    /**
     * Create a new parser that handles the provided rules JSON.
     * @param \Pgsql\Connection $pgsqlConnection
     */
    public function __construct(\Pgsql\Connection $pgsqlConnection)
    {
        $this->m_pgsqlConnection = $pgsqlConnection;
    }


    /**
     * Return the SQL statement to select from the specified table.
     * @param string $tableName - the name of the table we wish to select from.
     * @param string $queryBuilderJsonOutputString - the query builder JSON output string that we wish to convert.
     * @return string - the SQL.
     * @throws ExceptionInvalidConjunction
     */
    public function getSelectStatement(string $tableName, string $queryBuilderJsonOutputString) : string
    {
        $escapedTableName = pg_escape_identifier($this->m_pgsqlConnection, $tableName);
        return "SELECT * FROM {$escapedTableName} WHERE " . $this->getSql($queryBuilderJsonOutputString) . ";";
    }


    /**
     * Return the SQL representation of the JSON structure.
     * @return string - the SQL.
     * @throws ExceptionInvalidConjunction - if rules contain an invalid conjunction.
     */
    public function getSql(string $queryBuilderJsonOutputString) : string
    {
        $ruleGroup = $this->convertJsonToRuleGroup($queryBuilderJsonOutputString);
        return (string)$ruleGroup;
    }


    /**
     * Helper method that converts the JSON to a RuleGroup object.
     * @param string $rulesJsonString
     * @return RuleGroup
     * @throws ExceptionInvalidConjunction|Exception
     */
    private function convertJsonToRuleGroup(string $rulesJsonString) : RuleGroup
    {
        $arrayRepresentation = json_decode($rulesJsonString, true, JSON_THROW_ON_ERROR);
        $valid = $arrayRepresentation['valid'];

        if ($valid === false)
        {
            throw new Exception("Cannot parse rules that are marked as invalid.");
        }

        return new RuleGroup($arrayRepresentation['condition'], $arrayRepresentation['rules'], $this->m_pgsqlConnection);
    }
}
