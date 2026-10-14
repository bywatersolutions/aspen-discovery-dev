<?php

namespace PhpStanRules;

use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<FunctionLike>
 */
class MaxMethodLengthRule implements Rule
{
    private int $maxLines;
    /** @var array<string, int> */
    private array $legacyLimits = [];

    public function __construct(int $maxLines = 100, string $limitsFilePath = '')
    {
        $this->maxLines = $maxLines;

        if ($limitsFilePath !== '' && file_exists($limitsFilePath)) {
            $json = file_get_contents($limitsFilePath);
            if ($json !== false) {
                $this->legacyLimits = json_decode($json, true) ?? [];
            }
        }
    }

    public function getNodeType(): string
    {
        return FunctionLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // 1. Resolve method/function name and identifier
        $identifier = $this->resolveIdentifier($node, $scope);
        if ($identifier === null) {
            return [];
        }

        // 2. Calculate length
        $startLine = $node->getStartLine();
        $endLine = $node->getEndLine();
        if ($startLine === -1 || $endLine === -1) {
            return [];
        }

        $length = $endLine - $startLine + 1;

        // 3. Compare with legacy limit or default
        $allowedMax = $this->legacyLimits[$identifier] ?? $this->maxLines;

        if ($length > $allowedMax) {
            return [
                RuleErrorBuilder::message(
                    sprintf(
                        '%s is %d lines long (maximum allowed is %d).',
                        $identifier,
                        $length,
                        $allowedMax
                    )
                )->build()
            ];
        }

        return [];
    }

    private function resolveIdentifier(FunctionLike $node, Scope $scope): ?string
    {
        // Standalone procedural function
        if ($node instanceof Function_) {
            return isset($node->namespacedName) 
                ? $node->namespacedName->toString() 
                : $node->name->toString();
        }

        // Method in class or trait
        if ($node instanceof ClassMethod) {
            $methodName = $node->name->toString();

            // Try Class
            $class = $scope->getClassReflection();
            if ($class !== null) {
                return $class->getName() . '::' . $methodName;
            }

            // Try Trait (when parsed outside a class context)
            $trait = $scope->getTraitReflection();
            if ($trait !== null) {
                return $trait->getName() . '::' . $methodName;
            }
        }

        return null;
    }
}