<?php

namespace Programster\QueryBuilderPgsqlDriver\Testing;

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/TestSettings.php');

use Programster\CoreLibs\Filesystem;

$autoloader = new \iRAP\Autoloader\Autoloader([__DIR__]);

$tests = Filesystem::getDirContents(
    $dir=__DIR__ . '/tests', 
    $recursive = true, 
    $includePath = false, 
    $onlyFiles = true
);


foreach ($tests as $testFilename)
{
    $testName = substr($testFilename, 0, -4);
    require_once(__DIR__ . "/tests/{$testFilename}");
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