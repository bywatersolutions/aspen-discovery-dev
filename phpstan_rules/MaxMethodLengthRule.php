<?php
namespace PhpStanRules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<ClassMethod>
 */
class MaxMethodLengthRule implements Rule
{
    private int $maxLines;

    public function __construct(int $maxLines = 50)
    {
        $this->maxLines = $maxLines;
    }

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $startLine = $node->getStartLine();
        $endLine = $node->getEndLine();
        $length = $endLine - $startLine + 1;

        if ($length > $this->maxLines) {
            // Crucial: Embedding $length in the error string ensures 
            // any modification to line count invalidates the baseline entry.
            return [
                RuleErrorBuilder::message(
                    sprintf(
                        'Method %s() is %d lines long (maximum allowed is %d).',
                        $node->name->toString(),
                        $length,
                        $this->maxLines
                    )
                )->build()
            ];
        }

        return [];
    }
}