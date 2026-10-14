<?php
// generate-limits.php
require __DIR__ . '/vendor/autoload.php';

use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;

// 1. CONFIGURE YOUR SETTINGS HERE
$maxDefault = 100; // Line threshold
$directoriesToScan = [
    __DIR__ . '/code/web', // Add or change paths here (e.g. __DIR__ . '/app', __DIR__ . '/lib')
];

// 2. INITIALIZE COMPATIBLE PARSER (Supports PhpParser v4 & v5)
$factory = new ParserFactory();
if (method_exists($factory, 'createForHostVersion')) {
    $parser = $factory->createForHostVersion(); // v5
} else {
    $parser = $factory->create(ParserFactory::PREFER_PHP7); // v4
}

// 3. DEFINE AST VISITOR
class MethodLimitsVisitor extends NodeVisitorAbstract
{
    public array $limits = [];
    public int $totalMethodsScanned = 0;
    private int $threshold;
    private ?string $currentClass = null;

    public function __construct(int $threshold)
    {
        $this->threshold = $threshold;
    }

    public function enterNode(Node $node)
    {
        // Capture Class, Trait, or Interface
        if ($node instanceof Node\Stmt\Class_ || $node instanceof Node\Stmt\Trait_ || $node instanceof Node\Stmt\Interface_) {
            if (isset($node->namespacedName)) {
                $this->currentClass = $node->namespacedName->toString();
            } elseif ($node->name !== null) {
                $this->currentClass = $node->name->toString();
            } else {
                $this->currentClass = 'AnonymousClass';
            }
        }

        // Capture Class Methods
        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->totalMethodsScanned++;
            $length = $node->getEndLine() - $node->getStartLine() + 1;

            if ($length > $this->threshold) {
                $className = $this->currentClass ?? 'UnknownClass';
                $key = $className . '::' . $node->name->toString();
                $this->limits[$key] = $length;
            }
        }

        // Capture Standalone Procedural Functions
        if ($node instanceof Node\Stmt\Function_) {
            $this->totalMethodsScanned++;
            $length = $node->getEndLine() - $node->getStartLine() + 1;

            if ($length > $this->threshold) {
                $key = isset($node->namespacedName) 
                    ? $node->namespacedName->toString() 
                    : $node->name->toString();
                $this->limits[$key] = $length;
            }
        }
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_ || $node instanceof Node\Stmt\Trait_ || $node instanceof Node\Stmt\Interface_) {
            $this->currentClass = null;
        }
    }
}

// 4. SCAN FILES
$traverser = new NodeTraverser();
$traverser->addVisitor(new NameResolver()); // Resolves full namespaces
$visitor = new MethodLimitsVisitor($maxDefault);
$traverser->addVisitor($visitor);

$filesScanned = 0;

foreach ($directoriesToScan as $dir) {
    if (!is_dir($dir)) {
        echo "Warning: Directory not found: {$dir}\n";
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $filesScanned++;
            try {
                $code = file_get_contents($file->getPathname());
                $stmts = $parser->parse($code);
                if ($stmts !== null) {
                    $traverser->traverse($stmts);
                }
            } catch (\Throwable $e) {
                // Skips syntax errors in problematic legacy files
            }
        }
    }
}

// 5. SAVE RESULTS
ksort($visitor->limits);
file_put_contents(
    __DIR__ . '/legacy-limits.json', 
    json_encode($visitor->limits, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

// 6. DIAGNOSTIC SUMMARY
echo "--- Summary ---\n";
echo "Files scanned:           {$filesScanned}\n";
echo "Total methods checked:   {$visitor->totalMethodsScanned}\n";
echo "Methods exceeding {$maxDefault} lines: " . count($visitor->limits) . "\n";