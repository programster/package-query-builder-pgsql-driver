<?php

/*
 * An object to represent a group of rules, all or any of which must match, such as "a" = 1 AND ("b" = 2 OR "c" = 3).
 */

namespace Programster\QueryBuilderPgsqlDriver;


final readonly class RuleGroup implements \Stringable
{
    private string $m_sql;


    /**
     * Create a group of rules from jQuery QueryBuilder's JSON output. The group, and every rule and group in it, is
     * checked here, so a group that exists can always be turned into SQL.
     *
     * @param array<mixed> $input - the decoded group. It needs a "condition" (AND or OR) and a non-empty list of
     * "rules", each of which is either a rule or another group, and can have "not" set to true, to match when the
     * group doesn't. Its other keys (e.g. "valid") aren't used.
     *
     * @param \PgSql\Connection $pgsqlConnection - the connection to the database, which the values are escaped for.
     *
     * @param AllowedFields $allowedFields - the fields the rules, and the rules in its groups, may filter on.
     *
     * @throws ExceptionFieldNotAllowed - if a rule filters on a field that isn't one of the allowed fields.
     * @throws ExceptionInvalidConjunction - if the group's condition, or a group's within it, isn't AND or OR.
     * @throws ExceptionUnrecognizedOperator - if a rule uses an operator that isn't one of QueryBuilder's.
     * @throws ExceptionInvalidRules - if the group, or anything in it, isn't in the shape QueryBuilder's output has.
     */
    public function __construct(array $input, \PgSql\Connection $pgsqlConnection, AllowedFields $allowedFields)
    {
        $conjunctionName = $input['condition'] ?? null;
        $rules = $input['rules'] ?? null;
        $isNegated = $input['not'] ?? false;

        if (!is_string($conjunctionName))
        {
            throw new ExceptionInvalidRules("Every group needs a \"condition\", which must be AND or OR.");
        }

        $conjunction = Conjunction::tryFrom($conjunctionName);

        if ($conjunction === null)
        {
            throw new ExceptionInvalidConjunction($conjunctionName);
        }

        if (!is_array($rules) || !array_is_list($rules) || count($rules) === 0)
        {
            throw new ExceptionInvalidRules("Every group needs \"rules\", which must be a non-empty list.");
        }

        if (!is_bool($isNegated))
        {
            throw new ExceptionInvalidRules("A group's \"not\" must be true or false.");
        }

        $parts = [];

        foreach ($rules as $rule)
        {
            if (!is_array($rule))
            {
                throw new ExceptionInvalidRules("Every item in a group's \"rules\" must be a rule or a group.");
            }

            // A group in a group is wrapped in brackets, so AND and OR are applied in the order the groups show.
            if (array_key_exists('rules', $rule) || array_key_exists('condition', $rule))
            {
                $parts[] = "(" . new RuleGroup($rule, $pgsqlConnection, $allowedFields) . ")";
            }
            else
            {
                $parts[] = (string)new Condition($rule, $pgsqlConnection, $allowedFields);
            }
        }

        $sql = implode(" {$conjunction->value} ", $parts);
        $this->m_sql = $isNegated ? "NOT ({$sql})" : $sql;
    }


    /**
     * Get the group as SQL, e.g. "a" = 1 AND ("b" = 2 OR "c" = 3)
     * @return string
     */
    public function __toString(): string
    {
        return $this->m_sql;
    }
}
