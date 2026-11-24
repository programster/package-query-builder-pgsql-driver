<?php

/*
 * Turns a document's examples into a script and runs it, so the docs can't drift from what the code does:
 *
 * - its php code blocks run in order, as one script, in a separate PHP process, with a use statement added for every
 *   class in the package, and $conn set to a connection to the test database (a PgSql\Connection), which the
 *   examples say comes from pg_connect().
 *
 * - a line of the form "// $sql is: ..." checks that $sql is exactly what follows "is: ", so the SQL the docs show is
 *   the SQL the code gives.
 *
 * - a php block whose first line is "// Throws SomeException" must throw that exception (which can be given by its
 *   short name, for the package's own exceptions).
 */

namespace Programster\QueryBuilderPgsqlDriver\Testing;


final readonly class DocsExampleRunner
{
    private string $m_repositoryDirectory;


    /**
     * @param string $repositoryDirectory - the package's root folder, which holds vendor/autoload.php.
     */
    public function __construct(string $repositoryDirectory)
    {
        $this->m_repositoryDirectory = $repositoryDirectory;
    }


    /**
     * Count the php code blocks in a document, so a test can tell whether there's anything to run.
     * @param string $documentPath - the Markdown file.
     * @return int
     */
    public function countExamples(string $documentPath): int
    {
        return count($this->getPhpBlocks($documentPath));
    }


    /**
     * Run a document's examples, and throw if they fail.
     * @param string $documentPath - the Markdown file.
     * @return void
     * @throws \Exception - if the script fails, with its output.
     */
    public function run(string $documentPath): void
    {
        $script = $this->buildScript($this->getPhpBlocks($documentPath));
        $scriptPath = tempnam(sys_get_temp_dir(), 'pgsql-driver-docs-');

        if ($scriptPath === false)
        {
            throw new \Exception("Couldn't create a temporary file for the examples in {$documentPath}.");
        }

        file_put_contents($scriptPath, $script);
        $command = escapeshellarg(PHP_BINARY) . ' -d zend.assertions=1 -d assert.exception=1 '
            . escapeshellarg($scriptPath) . ' 2>&1';

        exec($command, $outputLines, $exitCode);
        unlink($scriptPath);

        if ($exitCode !== 0)
        {
            $output = implode("\n", array_slice($outputLines, -20));
            $name = basename($documentPath);
            throw new \Exception("The examples in {$name} failed (exit code {$exitCode}):\n{$output}");
        }
    }


    /**
     * Get the contents of a document's php code blocks, in order.
     * @param string $documentPath - the Markdown file.
     * @return list<string>
     * @throws \Exception - if the document can't be read.
     */
    private function getPhpBlocks(string $documentPath): array
    {
        $markdown = file_get_contents($documentPath);

        if ($markdown === false)
        {
            throw new \Exception("Couldn't read {$documentPath}.");
        }

        preg_match_all('/^```php\n(.*?)^```/ms', $markdown, $matches);
        return $matches[1];
    }


    /**
     * Build the script for a document's examples.
     * @param list<string> $blocks - the php code blocks.
     * @return string
     */
    private function buildScript(array $blocks): string
    {
        $packageUses = $this->getPackageUseStatements();
        $script = "<?php\n\nrequire_once(" . var_export($this->m_repositoryDirectory . '/vendor/autoload.php', true)
            . ");\n\n" . implode("\n", $packageUses) . "\n\n"
            . "\$conn = \\Programster\\QueryBuilderPgsqlDriver\\Testing\\TestDatabase::getConnection()"
            . "->getResource();\n\n";

        foreach ($blocks as $block)
        {
            $script .= $this->prepareBlock($block, $packageUses) . "\n\n";
        }

        return $script;
    }


    /**
     * Prepare one block to go in the script: remove what the script already has (the opening tag, the autoloader and
     * the package's use statements), turn each "// $sql is: ..." line into a check, and if it should throw, check that
     * it does.
     *
     * @param string $block - the block's code.
     *
     * @param list<string> $packageUses - the use statements the script already has.
     *
     * @return string
     */
    private function prepareBlock(string $block, array $packageUses): string
    {
        $keptLines = [];

        foreach (explode("\n", $block) as $line)
        {
            $isOpeningTag = (trim($line) === '<?php');
            $isAutoloader = str_contains($line, 'vendor/autoload.php');
            $isPackageUse = in_array(trim($line), $packageUses, true);

            if (preg_match('#^\s*// (\$\w+) is: (.*)$#', $line, $matches) === 1)
            {
                [, $variable, $expectedValue] = $matches;
                $expected = var_export($expectedValue, true);
                $message = "'Expected {$variable} to be ' . {$expected} . ', but it was ' . {$variable}";
                $keptLines[] = "assert({$variable} === {$expected}, {$message});";
            }
            elseif (!$isOpeningTag && !$isAutoloader && !$isPackageUse)
            {
                $keptLines[] = $line;
            }
        }

        $code = implode("\n", $keptLines);

        if (preg_match('#^\s*// Throws (\S+)#', $code, $matches) === 1)
        {
            $exception = var_export($matches[1], true);

            $code = "\$threw = false;\ntry\n{\n{$code}\n}\ncatch (\\Throwable \$e)\n{\n"
                . "    \$expected = class_exists({$exception}) ? {$exception} "
                . ": 'Programster\\\\QueryBuilderPgsqlDriver\\\\' . {$exception};\n"
                . "    if (!(\$e instanceof \$expected)) { throw \$e; }\n"
                . "    \$threw = true;\n}\n"
                . "if (!\$threw) { throw new \\Exception('Expected ' . {$exception} . ' to be thrown.'); }";
        }

        return $code;
    }


    /**
     * Get a use statement for every class, interface and enum in the package's src/ folder.
     * @return list<string>
     */
    private function getPackageUseStatements(): array
    {
        $paths = glob($this->m_repositoryDirectory . '/src/*.php');

        if ($paths === false)
        {
            throw new \Exception("Couldn't list the package's classes in its src/ folder.");
        }

        $uses = [];

        foreach ($paths as $path)
        {
            $uses[] = "use Programster\\QueryBuilderPgsqlDriver\\" . basename($path, '.php') . ";";
        }

        sort($uses);
        return $uses;
    }
}
