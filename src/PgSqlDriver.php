<?php

/*
 * A driver for the parser that works against a PostgreSQL database.
 */

namespace Programster\QueryBuilderPgsqlDriver;

use Programster\PgsqlLib\PgsqlLib;
use Programster\QueryBuilderParser\InterfaceParserDriver;


final readonly class PgSqlDriver implements InterfaceParserDriver
{
    private \PgSql\Connection $m_pgsqlConnection;
    private AllowedFields $m_allowedFields;


    /**
     * Create a driver that turns jQuery QueryBuilder's JSON output into SQL for a PostgreSQL database.
     *
     * @param \PgSql\Connection $pgsqlConnection - the connection to the database, which the values are escaped for.
     *
     * @param AllowedFields $allowedFields - the fields the rules may filter on, e.g.
     * AllowedFields::createUsingFields('name', 'age'). A rule on any other field is rejected.
     */
    public function __construct(\PgSql\Connection $pgsqlConnection, AllowedFields $allowedFields)
    {
        $this->m_pgsqlConnection = $pgsqlConnection;
        $this->m_allowedFields = $allowedFields;
    }


    /**
     * Get the SQL statement that selects the rows of a table that match the rules.
     * @param string $tableName - the name of the table to select from.
     * @param string $queryBuilderJsonOutputString - the JSON from QueryBuilder's getRules(), to convert.
     * @return string - the SQL, e.g. SELECT * FROM "users" WHERE "name" = 'bob';
     * @throws \JsonException - if the JSON can't be decoded.
     * @throws ExceptionFieldNotAllowed - if a rule filters on a field that isn't one of the allowed fields.
     * @throws ExceptionInvalidRules - if the rules are marked as invalid, or aren't in the shape QueryBuilder's are.
     * @throws ExceptionInvalidConjunction - if a group's condition isn't AND or OR.
     * @throws ExceptionUnrecognizedOperator - if a rule uses an operator that isn't one of QueryBuilder's.
     */
    public function getSelectStatement(string $tableName, string $queryBuilderJsonOutputString) : string
    {
        $escapedTableName = PgsqlLib::escapeIdentifier($this->m_pgsqlConnection, $tableName);
        return "SELECT * FROM {$escapedTableName} WHERE " . $this->getSql($queryBuilderJsonOutputString) . ";";
    }


    /**
     * Get the SQL for the rules, which goes in a WHERE clause.
     * @param string $queryBuilderJsonOutputString - the JSON from QueryBuilder's getRules(), to convert.
     * @return string - the SQL, e.g. "name" = 'bob' AND ("age" < 18 OR "age" > 65)
     * @throws \JsonException - if the JSON can't be decoded.
     * @throws ExceptionFieldNotAllowed - if a rule filters on a field that isn't one of the allowed fields.
     * @throws ExceptionInvalidRules - if the rules are marked as invalid, or aren't in the shape QueryBuilder's are.
     * @throws ExceptionInvalidConjunction - if a group's condition isn't AND or OR.
     * @throws ExceptionUnrecognizedOperator - if a rule uses an operator that isn't one of QueryBuilder's.
     */
    public function getSql(string $queryBuilderJsonOutputString) : string
    {
        $rules = json_decode($queryBuilderJsonOutputString, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($rules))
        {
            throw new ExceptionInvalidRules("The JSON must be an object, as QueryBuilder's getRules() gives.");
        }

        // QueryBuilder marks the rules as invalid when the user hasn't finished filling them in.
        if (array_key_exists('valid', $rules) && $rules['valid'] !== true)
        {
            throw new ExceptionInvalidRules("Cannot parse rules that are marked as invalid.");
        }

        return (string)new RuleGroup($rules, $this->m_pgsqlConnection, $this->m_allowedFields);
    }
}
