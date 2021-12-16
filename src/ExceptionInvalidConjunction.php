<?php

namespace Programster\QueryBuilderPgsqlDriver;

class ExceptionInvalidConjunction extends \Exception
{
    public function __construct(string $conjunction)
    {
        parent::__construct("Invalid conjunction provided: {$conjunction}. Must be one of 'AND' or 'OR'");
    }
}

