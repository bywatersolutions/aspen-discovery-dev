<?php
namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<BinaryOp>
 */
class DisallowLooseScalarComparisonRule implements Rule
{
    public function getNodeType(): string
    {
        return BinaryOp::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Equal && !$node instanceof NotEqual) {
            return [];
        }

        $leftType = $scope->getType($node->left);
        $rightType = $scope->getType($node->right);

        // Allow loose comparison only if both sides are known to be objects
        if ($leftType->isObject()->yes() && $rightType->isObject()->yes()) {
            return [];
        }

        $operator = $node instanceof Equal ? '==' : '!=';
        $suggested = $node instanceof Equal ? '===' : '!==';

        return [
            RuleErrorBuilder::message(
                sprintf(
                    'Loose comparison operator "%s" is disallowed for non-object types. Use "%s" or explicit type casting.',
                    $operator,
                    $suggested
                )
            )
            ->identifier('comparison.looseScalar')
            ->line($node->getStartLine())
            ->build(),
        ];
    }
}