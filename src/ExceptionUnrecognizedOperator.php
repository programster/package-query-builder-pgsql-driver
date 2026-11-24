<?php

/*
 * Thrown when a rule uses an operator that isn't one of jQuery QueryBuilder's (see Operator).
 */

namespace Programster\QueryBuilderPgsqlDriver;


class ExceptionUnrecognizedOperator extends \Exception
{
    public function __construct(string $operator)
    {
        parent::__construct(
            "Unrecognised operator: {$operator}. Please check your JSON structure, or create an issue for this."
        );
    }
}
