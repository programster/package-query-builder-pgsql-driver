<?php

/*
 * The operators a rule in jQuery QueryBuilder's JSON output can use, backed by the name QueryBuilder gives each one.
 * See https://querybuilder.js.org/#operators
 */

namespace Programster\QueryBuilderPgsqlDriver;


enum Operator: string
{
    case EQUAL = 'equal';
    case NOT_EQUAL = 'not_equal';
    case IN = 'in';
    case NOT_IN = 'not_in';
    case LESS = 'less';
    case LESS_OR_EQUAL = 'less_or_equal';
    case GREATER = 'greater';
    case GREATER_OR_EQUAL = 'greater_or_equal';
    case BETWEEN = 'between';
    case NOT_BETWEEN = 'not_between';
    case BEGINS_WITH = 'begins_with';
    case NOT_BEGINS_WITH = 'not_begins_with';
    case CONTAINS = 'contains';
    case NOT_CONTAINS = 'not_contains';
    case ENDS_WITH = 'ends_with';
    case NOT_ENDS_WITH = 'not_ends_with';
    case IS_EMPTY = 'is_empty';
    case IS_NOT_EMPTY = 'is_not_empty';
    case IS_NULL = 'is_null';
    case IS_NOT_NULL = 'is_not_null';


    /**
     * Get the SQL operator this operator is written as, e.g. ">=" for GREATER_OR_EQUAL, or "NOT LIKE" for
     * NOT_CONTAINS. IS_EMPTY and IS_NOT_EMPTY are each written as two comparisons, which have the operators of
     * IS_NULL and "=", or IS_NOT_NULL and "!=".
     *
     * @return string
     */
    public function getSqlOperator(): string
    {
        return match ($this) {
            Operator::EQUAL => '=',
            Operator::NOT_EQUAL => '!=',
            Operator::IN => 'IN',
            Operator::NOT_IN => 'NOT IN',
            Operator::LESS => '<',
            Operator::LESS_OR_EQUAL => '<=',
            Operator::GREATER => '>',
            Operator::GREATER_OR_EQUAL => '>=',
            Operator::BETWEEN => 'BETWEEN',
            Operator::NOT_BETWEEN => 'NOT BETWEEN',
            Operator::BEGINS_WITH, Operator::CONTAINS, Operator::ENDS_WITH => 'LIKE',
            Operator::NOT_BEGINS_WITH, Operator::NOT_CONTAINS, Operator::NOT_ENDS_WITH => 'NOT LIKE',
            Operator::IS_NULL, Operator::IS_EMPTY => 'IS NULL',
            Operator::IS_NOT_NULL, Operator::IS_NOT_EMPTY => 'IS NOT NULL',
        };
    }
}
