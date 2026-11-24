<?php

namespace Programster\QueryBuilderPgsqlDriver\Testing;

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/TestSettings.php');

$testPaths = glob(__DIR__ . '/tests/*.php');

if ($testPaths === false)
{
    throw new \Exception("Failed to list the tests in " . __DIR__ . "/tests");
}

foreach ($testPaths as $testPath)
{
    $testName = basename($testPath, '.php');
    require_once($testPath);
    $testName = __NAMESPACE__ . "\Tests\\{$testName}";

    /* @var $testToRun AbstractTest */
    $testToRun = new $testName();
    $testToRun->runTest();
    
    if ($testToRun->getPassed())
    {
        print $testName . ": \e[32mPASSED\e[0m" . PHP_EOL;
    }
    else 
    {
        print $testName . ": \e[31mFAILED\e[0m - " . implode(PHP_EOL, $testToRun->getErrorMessages()) . PHP_EOL;
    }
}