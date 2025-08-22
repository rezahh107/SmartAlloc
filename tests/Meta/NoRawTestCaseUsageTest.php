<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Meta;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class NoRawTestCaseUsageTest extends TestCase
{
    /** @test */
    public function all_tests_must_extend_base_testcase(): void
    {
        $bad = [];
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(\dirname(__DIR__), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();

            // Optional exemptions (keep narrow)
            if ($path === __FILE__ || \basename($path) === 'BaseTestCase.php' || \str_contains($path, \DIRECTORY_SEPARATOR . 'Fixtures' . \DIRECTORY_SEPARATOR)) {
                continue;
            }

            $code = \file_get_contents($path);
            if ($code === false) {
                continue;
            }

            try {
                $ast = $parser->parse($code);
                if (!$ast) { continue; }

                $traverser = new NodeTraverser();
                $traverser->addVisitor(new NameResolver());
                $ast = $traverser->traverse($ast);

                foreach ($ast as $node) {
                    $this->scanNode($node, $path, $bad);
                }
            } catch (Error $e) {
                $bad[] = $path . ' (parse error: ' . $e->getMessage() . ')';
            }
        }

        $this->assertSame(
            [],
            $bad,
            "These test files extend raw PHPUnit TestCase (directly or via alias) instead of SmartAlloc\\Tests\\BaseTestCase:\n" .
            \implode("\n", $bad)
        );
    }

    private function scanNode(Node $node, string $path, array &$bad): void
    {
        // Recurse into statements that can contain classes/namespaces
        if ($node instanceof Node\Stmt\Namespace_) {
            foreach ($node->stmts as $stmt) {
                $this->scanNode($stmt, $path, $bad);
            }
            return;
        }

        if ($node instanceof Node\Stmt\Class_) {
            // Skip anonymous classes
            if ($node->name === null) {
                return;
            }

            $extends = $node->extends;
            if (!$extends instanceof Node\Name) {
                return;
            }

            $fqn = $extends->toString(); // NameResolver makes this fully qualified

            // Allow only our shared base test
            if ($fqn === 'SmartAlloc\\Tests\\BaseTestCase') {
                return;
            }

            // Flag any PHPUnit\Framework\TestCase usage (direct or aliased)
            if ($fqn === 'PHPUnit\\Framework\\TestCase') {
                $bad[] = $path;
            }
        }

        // Recurse into child nodes
        foreach ($node->getSubNodeNames() as $name) {
            $sub = $node->$name ?? null;
            if ($sub instanceof Node) {
                $this->scanNode($sub, $path, $bad);
            } elseif (\is_array($sub)) {
                foreach ($sub as $s) {
                    if ($s instanceof Node) {
                        $this->scanNode($s, $path, $bad);
                    }
                }
            }
        }
    }
}
