<?php

/*
 * Abstract class all tests should extend.
 */

namespace Programster\QueryBuilderPgsqlDriver\Testing;


use Exception;

abstract class AbstractTest
{
    protected bool $m_passed = false;
    protected array $m_errorMessages = [];


    abstract public function getDescription() : string;
    abstract public function run();


    public function getErrorMessages() : array
    {
        return $this->m_errorMessages;
    }


    public function runTest()
    {
        try
        {
            $this->run();
        }
        catch (Exception $ex)
        {
            $this->m_passed = false;
            $this->m_errorMessages[] = $ex->getMessage();
        }
    }


    public function getPassed(): bool { return $this->m_passed; }
}