<?php

namespace Programster\QueryBuilderPgsqlDriver;

class ExceptionUnrecognizedOperator extends \Exception
{
    public function __construct(string $operator)
    {
        parent::__construct("Unrecognized operator: {$operator}. Please check your JSON structure, or create an issue for this.");
    }
}

