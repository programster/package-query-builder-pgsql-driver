<?php

/*
 * An object to represent a condition, such as "x > y" or "a = b"
 */

namespace Programster\QueryBuilderPgsqlDriver;

use Programster\PgsqlLib\PgsqlLib;


final readonly class Condition implements \Stringable
{
    private string $m_field;
    private Operator $m_operator;
    private mixed $m_value;
    private \PgSql\Connection $m_connection;
    private string $m_sql;


    /**
     * Create a condition from one rule in jQuery QueryBuilder's JSON output, e.g. "age" > 3. The rule is checked
     * here, so a condition that exists can always be turned into SQL.
     *
     * @param array<mixed> $input - the decoded rule. It needs a "field" and an "operator", and a "value" for every
     * operator other than is_empty, is_not_empty, is_null and is_not_null. Its other keys (e.g. "id" and "type") aren't
     * used.
     *
     * @param \PgSql\Connection $pgsqlConnection - the connection to the database, which the values are escaped for.
     *
     * @param AllowedFields $allowedFields - the fields the rule may filter on.
     *
     * @throws ExceptionFieldNotAllowed - if the field isn't one of the allowed fields.
     * @throws ExceptionUnrecognizedOperator - if the operator isn't one of QueryBuilder's.
     * @throws ExceptionInvalidRules - if the field is missing or empty, or the value isn't what the operator needs.
     */
    public function __construct(array $input, \PgSql\Connection $pgsqlConnection, AllowedFields $allowedFields)
    {
        $field = $input['field'] ?? null;
        $operatorName = $input['operator'] ?? null;

        if (!is_string($field) || $field === '')
        {
            throw new ExceptionInvalidRules("Every rule needs a \"field\", which must be a non-empty string.");
        }

        if (!$allowedFields->isAllowed($field))
        {
            throw new ExceptionFieldNotAllowed($field);
        }

        if (!is_string($operatorName))
        {
            throw new ExceptionInvalidRules("The rule on \"{$field}\" needs an \"operator\", which must be a string.");
        }

        $operator = Operator::tryFrom($operatorName);

        if ($operator === null)
        {
            throw new ExceptionUnrecognizedOperator($operatorName);
        }

        $this->m_field = $field;
        $this->m_operator = $operator;
        $this->m_value = $input['value'] ?? null;
        $this->m_connection = $pgsqlConnection;
        $this->m_sql = $this->createSql();
    }


    /**
     * Get the condition as SQL, e.g. "age" > 3
     * @return string
     */
    public function __toString(): string
    {
        return $this->m_sql;
    }


    /**
     * Create the condition's SQL, checking its value is what its operator needs.
     * @return string
     * @throws ExceptionInvalidRules - if the value isn't what the operator needs.
     */
    private function createSql(): string
    {
        $field = PgsqlLib::escapeIdentifier($this->m_connection, $this->m_field);
        $left = "{$field} {$this->m_operator->getSqlOperator()}";

        return match ($this->m_operator) {
            Operator::IS_NULL, Operator::IS_NOT_NULL => $left,
            Operator::IS_EMPTY => "({$left} OR {$field} = '')",
            Operator::IS_NOT_EMPTY => "({$left} AND {$field} != '')",
            Operator::IN, Operator::NOT_IN => "{$left} ({$this->escapeList()})",
            Operator::BETWEEN, Operator::NOT_BETWEEN => "{$left} {$this->escapeRange()}",
            Operator::BEGINS_WITH, Operator::NOT_BEGINS_WITH => "{$left} {$this->escapePattern(false, true)}",
            Operator::CONTAINS, Operator::NOT_CONTAINS => "{$left} {$this->escapePattern(true, true)}",
            Operator::ENDS_WITH, Operator::NOT_ENDS_WITH => "{$left} {$this->escapePattern(true, false)}",
            default => "{$left} {$this->escapeSingleValue()}",
        };
    }


    /**
     * Escape the value of an operator that compares against one value, such as equal or greater.
     * @return string|int
     * @throws ExceptionInvalidRules - if the value isn't a string, number or boolean.
     */
    private function escapeSingleValue(): string|int
    {
        return PgsqlLib::escapeValue($this->m_connection, $this->requireScalar($this->m_value));
    }


    /**
     * Escape the values of in or not_in, separated by commas.
     * @return string
     * @throws ExceptionInvalidRules - if the value isn't a non-empty list of strings, numbers or booleans.
     */
    private function escapeList(): string
    {
        if (!is_array($this->m_value) || !array_is_list($this->m_value) || count($this->m_value) === 0)
        {
            throw new ExceptionInvalidRules("{$this->describe()} needs a value that's a non-empty list.");
        }

        $escapedValues = [];

        foreach ($this->m_value as $value)
        {
            $escapedValues[] = PgsqlLib::escapeValue($this->m_connection, $this->requireScalar($value));
        }

        return implode(",", $escapedValues);
    }


    /**
     * Escape the two values of between or not_between, as the SQL that goes after BETWEEN, e.g. 5 AND 6.
     * @return string
     * @throws ExceptionInvalidRules - if the value isn't a list of two strings, numbers or booleans.
     */
    private function escapeRange(): string
    {
        if (!is_array($this->m_value) || !array_is_list($this->m_value) || count($this->m_value) !== 2)
        {
            throw new ExceptionInvalidRules("{$this->describe()} needs a value that's a list of exactly two values.");
        }

        $lowest = PgsqlLib::escapeValue($this->m_connection, $this->requireScalar($this->m_value[0]));
        $highest = PgsqlLib::escapeValue($this->m_connection, $this->requireScalar($this->m_value[1]));
        return "{$lowest} AND {$highest}";
    }


    /**
     * Escape the value of a LIKE operator (e.g. contains) as a pattern. Any % or _ in the value is escaped, so it
     * only matches itself, rather than being a wildcard.
     *
     * @param bool $wildcardBefore - whether the pattern matches anything before the value.
     *
     * @param bool $wildcardAfter - whether the pattern matches anything after the value.
     *
     * @return string
     *
     * @throws ExceptionInvalidRules - if the value isn't a string or number.
     */
    private function escapePattern(bool $wildcardBefore, bool $wildcardAfter): string
    {
        if (!is_string($this->m_value) && !is_int($this->m_value) && !is_float($this->m_value))
        {
            throw new ExceptionInvalidRules("{$this->describe()} needs a value that's a string or a number.");
        }

        // A backslash is LIKE's default escape character, so it's escaped too.
        $text = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string)$this->m_value);
        $pattern = ($wildcardBefore ? '%' : '') . $text . ($wildcardAfter ? '%' : '');

        // pg_escape_literal() writes a string with a backslash in it as E'...', with a space before it.
        return ltrim((string)PgsqlLib::escapeValue($this->m_connection, $pattern));
    }


    /**
     * Check one of the values is a string, number or boolean, which is all a rule can compare against.
     * @param mixed $value - the value to check.
     * @return string|int|float|bool
     * @throws ExceptionInvalidRules - if it isn't.
     */
    private function requireScalar(mixed $value): string|int|float|bool
    {
        if ($value === null)
        {
            $message = "{$this->describe()} has a null value. Use is_null or is_not_null to compare with NULL.";
            throw new ExceptionInvalidRules($message);
        }

        if (!is_scalar($value))
        {
            throw new ExceptionInvalidRules("{$this->describe()} needs values that are strings, numbers or booleans.");
        }

        return $value;
    }


    /**
     * Describe the rule, for an error message, e.g. The "between" rule on "age"
     * @return string
     */
    private function describe(): string
    {
        return "The \"{$this->m_operator->value}\" rule on \"{$this->m_field}\"";
    }
}
