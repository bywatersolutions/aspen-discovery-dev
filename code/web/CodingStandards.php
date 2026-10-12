<?php

class TestClass {

    /**
     * CLEAN CODE RULES
     */

    public function failIfStatementAssignment(array $data): string
    {
        // Bad practice: Assigning $result inside the if condition
        if ($result = array_pop($data)) {
            return "Last item was: " . $result;
        }

        return "Array was empty";
    }

    public function failDuplicatedArrayKey(): array
    {
        return [
            'status' => 'success',
            'code' => 200,
            // Bad practice: 'status' key is duplicated
            'status' => 'error' 
        ];
    }

    public function failErrorControlOperator(): string
    {
        // Bad practice: Using @ to suppress the warning if the file doesn't exist
        $content = @file_get_contents('/path/to/missing/file.txt');
        
        return $content ?: 'Default content';
    }

    public function failUndefinedVariable(){
        echo $x;
    }

    /**
     * CODE SIZE RULES
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

        if ($a) { $score += 1; }
        if ($b) { $score += 2; }
        if ($c) { $score += 3; }
        if ($d) { $score += 4; }
        if ($e) { $score += 5; }
        if ($f) { $score += 6; }
        if ($g) { $score += 7; }
        if ($h) { $score += 8; }

        return $score;
    }

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
        echo "Hello";
        echo "Hello";
        echo "Hello";
    }

    function failExcessiveParameterLength(
        $a, $b, $c, $d, $e, $f, $g, $h, $i, $j, $k
    ) {
        $params = [$a, $b, $c, $d, $e, $f, $g, $h, $i, $j, $k];
        foreach($params as $param) echo $param;
    }

    /**
     * Design Rule Violations
     */
    function failEmptyCatchBlock(){
        try {
            throw new Exception("Error");
        } catch(Exception) {
            // Do nothing
        }
    }

    function failCountInLoopExpression(){
        $arr = [1,2,3,4,5];

        for($i=0; $i < count($arr); $i++) {
            // Count is called on every iteration, and it's possible
            // $arr might be modified in the loop. Considered bad practice.
        }
    }

    /**
     * Unused Code Rules
     */
    private $failUnusedPrivateField;

    private function failUnusedPrivateMethod(){
        // will trigger error with phpmd
    }

    public function unusedFormalParameter($x){
        // $x is not used, throws error
    }

    public function unusedLocalVariable(){
        // Assign variable and do nothing with it later on
        $x = 5;        
    }

    





    


    
}