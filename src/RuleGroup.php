<?php


namespace Programster\QueryBuilderPgsqlDriver;


use Stringable;

class RuleGroup implements Stringable
{
    private string $m_condition;
    private array $m_subRuleGroups;
    private array $m_subConditions;
    private \Pgsql\Connection $m_connection;


    /**
     * Create a new RuleGroup.
     * @param string $condition - the condition under which the rules must evaluate. "AND" for all of them, "OR" for
     * just one of them.
     * @param array $rules - the array of rules that form the rule group.
     * @param \Pgsql\Connection $pgsqlConnection - the postgresql database connection (used for escaping).
     * @throws ExceptionInvalidConjunction - if the "condition" provided was not a valid conjunction (e.g. "AND"/"OR")
     */
    public function __construct(string $condition, array $rules, \Pgsql\Connection $pgsqlConnection)
    {
        if (!in_array($condition, ["AND", "OR"]))
        {
            throw new ExceptionInvalidConjunction($condition);
        }

        $this->m_connection = $pgsqlConnection;
        $this->m_condition = $condition;
        $this->m_subRuleGroups = [];
        $this->m_subConditions = [];

        if (count($rules) > 0)
        {
            foreach ($rules as $rule)
            {
                if (isset($rule['condition']))
                {
                    $this->m_subRuleGroups[] = new RuleGroup($rule['condition'], $rule['rules'], $this->m_connection);
                }
                else
                {
                    $this->m_subConditions[] = new Condition($rule, $this->m_connection);
                }
            }
        }
    }


    public function __toString(): string
    {
        return implode(" {$this->m_condition} ", [...$this->m_subConditions, ...$this->m_subRuleGroups]);
    }
}