<?php

/*
 * Runs the PHP examples in the README and every page in docs/, so the documentation can't drift from what the code
 * does. See DocsExampleRunner.php.
 */

use Programster\QueryBuilderPgsqlDriver\Testing\DocsExampleRunner;
use Programster\QueryBuilderPgsqlDriver\Testing\SmokeTest;

/** @var SmokeTest $smoke */

$repositoryDirectory = dirname(__DIR__, 2);
$docsRunner = new DocsExampleRunner($repositoryDirectory);
$documents = glob("{$repositoryDirectory}/docs/*.md");

if ($documents === false)
{
    throw new \Exception("Couldn't list the documents in {$repositoryDirectory}/docs.");
}

$smoke->run("docs: the README's quick start has examples", function () use ($docsRunner, $repositoryDirectory): void {
    assert($docsRunner->countExamples("{$repositoryDirectory}/README.md") > 0, "the README has no php examples");
});

foreach (["{$repositoryDirectory}/README.md", ...$documents] as $document)
{
    $name = substr($document, strlen($repositoryDirectory) + 1);

    $smoke->run("docs: the examples in {$name} work", function () use ($docsRunner, $document): void {
        $docsRunner->run($document);
    });
}
