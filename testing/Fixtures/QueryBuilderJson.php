<?php

/*
 * Builds rules and groups in the shape jQuery QueryBuilder's getRules() gives them, for the tests.
 */

namespace Programster\QueryBuilderPgsqlDriver\Testing\Fixtures;

use Programster\QueryBuilderPgsqlDriver\Conjunction;
use Programster\QueryBuilderPgsqlDriver\Operator;


final class QueryBuilderJson
{
    /**
     * Create a rule, as QueryBuilder gives it.
     * @param string $field - the column the rule is on.
     * @param Operator $operator - the rule's operator.
     * @param mixed $value - the value it compares against, which QueryBuilder gives as null for is_null etc.
     * @return array<string, mixed>
     */
    public static function createRule(string $field, Operator $operator, mixed $value = null): array
    {
        return [
            'id' => $field,
            'field' => $field,
            'type' => 'string',
            'input' => 'text',
            'operator' => $operator->value,
            'value' => $value,
        ];
    }


    /**
     * Create a group of rules, as QueryBuilder gives it.
     * @param Conjunction $conjunction - whether all of the rules (AND) or any of them (OR) must match.
     * @param array<mixed> ...$rules - the rules and groups in the group.
     * @return array<string, mixed>
     */
    public static function createGroup(Conjunction $conjunction, array ...$rules): array
    {
        return ['condition' => $conjunction->value, 'rules' => $rules];
    }


    /**
     * Encode a group as the JSON QueryBuilder gives, which marks the rules as valid.
     * @param array<mixed> $group - the group, e.g. from createGroup().
     * @return string
     */
    public static function encode(array $group): string
    {
        return json_encode([...$group, 'valid' => true], JSON_THROW_ON_ERROR);
    }
}
