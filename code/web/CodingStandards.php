<?php

class CodingStandards {

    /**
     * CLEAN CODE RULES
     */

    /**
     * Problematic because:
     * 1. Readability & Ambiguity: Easily mistaken for an accidental equality check (`==` vs `=`),
     *    making code reviews and debugging harder.
     * 2. Concealed Side Effects: Blurs the line between evaluating state and mutating state.
     * 3. Falsy Value Traps: If `array_pop` returns a valid falsy value (such as `0`, `""`, or `false`),
     *    the condition evaluates to `false` and skips the block even though an element was retrieved.
     */
    public function failIfStatementAssignment(array $data): string
    {
        // Bad practice: Assigning $result inside the if condition
        if ($result = array_pop($data)) {
            return "Last item was: " . $result;
        }

        return "Array was empty";
    }

    /**
     * Problematic because:
     * 1. Silent Data Loss: PHP silently overwrites prior keys with the last declared value,
     *    discarding the initial assignment ('status' => 'success') without warning.
     * 2. Masked Errors: Almost always indicates a copy-paste mistake, accidental merge conflict,
     *    or dead code that leads to unexpected runtime state.
     */
    public function failDuplicatedArrayKey(): array
    {
        return [
            'status' => 'success',
            'code' => 200,
            // Bad practice: 'status' key is duplicated
            'status' => 'error' 
        ];
    }

    /**
     * Problematic because:
     * 1. Hides Root Causes: Blindly suppresses warnings for underlying infrastructure issues
     *    (e.g., file permissions, missing directories, disk full, network timeouts) instead of handling them.
     * 2. Debugging Nightmare: Strips out error context and stack traces when diagnosing production failures.
     * 3. Performance Overhead: Historically forces the PHP runtime to temporarily alter error-reporting levels.
     */
    public function failErrorControlOperator(): string
    {
        // Bad practice: Using @ to suppress the warning if the file doesn't exist
        $content = @file_get_contents('/path/to/missing/file.txt');
        
        return $content ?: 'Default content';
    }

    /**
     * Problematic because:
     * 1. Emits Warnings: In PHP 8+, reading an uninitialized variable raises an `E_WARNING`,
     *    polluting error logs or halting execution under strict error handling.
     * 2. Unpredictable State: Relies on implicit fallback to `null`, often masking typos or broken scope logic.
     */
    public function failUndefinedVariable(){
        echo $x;
    }

    /**
     * CODE SIZE RULES
     */

    /**
     * Problematic because:
     * 1. Exhaustive Testing Burden: Cyclomatic complexity measures linearly independent execution paths;
     *    high values require an excessive number of test cases to achieve full branch coverage.
     * 2. Cognitive Overload: Deeply nested or sprawling conditional ladders make it difficult for
     *    maintainers to trace logic mentally.
     * 3. Code Smells: High branching often flags a missed abstraction (e.g., polymorphism, lookup maps,
     *    or `match` expressions) and leads to copy-paste bugs (such as the duplicate `$x == "n"` branch here).
     */
    public function failCyclomaticComplexity(string $x){
        if($x == "a"){ 
            return; 
        } elseif($x == "b"){
            return;
        } elseif($x == "c"){ 
            return; 
        } elseif($x == "d"){
            return;
        } elseif($x == "e"){
             return; 
        } elseif($x == "f"){
            return;
        } elseif($x == "g"){ 
            return; 
        } elseif($x == "h"){
            return;
        } elseif($x == "i"){ 
            return; 
        } elseif($x == "j"){
            return;
        } elseif($x == "k"){
             return; 
        } elseif($x == "l"){
            return;
        } elseif($x == "n"){ 
            return; 
        } elseif($x == "n"){
            return;
        }
    }

    /**
     * Problematic because:
     * 1. Combinatorial Explosion: NPath complexity calculates the total number of possible acyclic
     *    execution paths. With 8 independent boolean branches, there are 2^8 = 256 unique paths through this method.
     * 2. Untestable Edge Cases: Verifying every combination of inputs becomes practically impossible,
     *    drastically raising the probability of undiscovered edge-case bugs.
     */
    function failNPathComplexity(
        bool $a,
        bool $b,
        bool $c,
        bool $d,
        bool $e,
        bool $f,
        bool $g,
        bool $h
    ): int {
        $score = 0;

        if ($a) {$score += 1; }
        if ($b) {$score += 2; }
        if ($c) {$score += 3; }
        if ($d) {$score += 4; }
        if ($e) {$score += 5; }
        if ($f) {$score += 6; }
        if ($g) {$score += 7; }
        if ($h) {$score += 8; }

        return $score;
    }

    /**
     * Problematic because:
     * 1. Single Responsibility Principle (SRP) Violation: Methods of excessive length almost always
     *    handle too many concerns at once.
     * 2. Maintainability & Reusability: Cannot be read in a single viewport, forcing high mental overhead
     *    to track scope and preventing reusable extraction of discrete logic blocks.
     */
    public function failExcessiveMethodLength(){
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
        echo "Hello";
    }

    /**
     * Problematic because:
     * 1. Brittle Call Signatures: Callers must remember the exact positional order of many parameters,
     *    making argument-swapping bugs extremely easy to introduce.
     * 2. High Coupling: Indicates missing domain objects; grouping these parameters into a dedicated
     *    Data Transfer Object (DTO) or Value Object produces a much cleaner and more extensible contract.
     */
    function failExcessiveParameterLength(
        $a, $b,$c, $d,$e, $f,$g, $h,$i, $j,$k
    ) {
        $params = [$a, $b,$c, $d,$e, $f,$g, $h,$i, $j,$k];
        foreach($params as $param) echo $param;
    }

    /**
     * Design Rule Violations
     */

    /**
     * Problematic because:
     * 1. Swallowed Failures: Silently ignores exceptions without logging, reporting, or recovering.
     * 2. Ghost Bugs: Leaves application state corrupted while proceeding as if execution succeeded,
     *    making root-cause analysis nearly impossible when downstream code breaks.
     */
    function failEmptyCatchBlock(){
        try {
            throw new Exception("Error");
        } catch(Exception) {} // Do not use empty catch blocks
    }

    /**
     * Problematic because:
     * 1. Performance Degradation: `count()` runs on every single iteration instead of being cached once,
     *    introducing unnecessary function-call overhead on each loop step.
     * 2. Mutation Vulnerability: If `$arr` is modified inside the loop body, the boundary condition
     *    shifts dynamically during runtime, risking infinite loops or skipped indices.
     */
    function failCountInLoopExpression(){
        $arr = [1,2,3,4,5];

        for($i=0; $i < count($arr);$i++) {
            // Count is called on every iteration, and it's possible
            // $arr might be modified in the loop. Considered bad practice.
        }
    }

    /**
     * Unused Code Rules
     */

    /**
     * Problematic because:
     * 1. Dead State: Declares private state that is never read or written within the class.
     * 2. Maintenance Overhead: Misleads future maintainers into thinking state is being tracked,
     *    wasting time during refactoring or code audits.
     */
    private $failUnusedPrivateField;

    /**
     * Problematic because:
     * 1. Dead Code: Because it is `private`, it cannot be invoked outside this class; if it is not called
     *    internally, it is entirely unreachable.
     * 2. Technical Debt: Increases codebase size and forces maintainers to update or preserve dead routines.
     */
    private function failUnusedPrivateMethod(){
        // will trigger error with phpmd
    }

    /**
     * Problematic because:
     * 1. Misleading API Contract: Callers supply an argument expecting it to influence behavior,
     *    when in reality it is ignored.
     * 2. Omitted Logic: Often indicates an incomplete feature where the developer forgot to hook up the variable.
     */
    public function unusedFormalParameter($x){
        // $x is not used, throws error
    }

    /**
     * Problematic because:
     * 1. Wasted Allocation: Performs an assignment that yields no result or side effect.
     * 2. Developer Confusion: Leaves future readers wondering if the variable was supposed to be returned,
     *    passed to another method, or used in an expression that was accidentally deleted.
     */
    public function unusedLocalVariable(){
        // Assign variable and do nothing with it later on
        $x = 5;        
    }

    public function testStrictComparisons2() {
        $left = new stdClass();
        $right = new stdClass();
        $left->value = "value";
        $right->value = "value";
        if($left === $right){
            echo "Test";
        }
    }
}