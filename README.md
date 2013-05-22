# pEigthP

### What it is
pEigthP is a basic implementation of LISP that can be embedded in a PHP program. It is designed to work closely with the underlying PHP script allowing values and functions to be passed between PHP and pEigthP with minimal effort. 

### Why I did it
Many years ago, when I started building websites, I wanted to try out writing a website in lisp, only to find that there are not all that many cheap hosting options available for the language. I eventually gave up and just built a PHP site instead, but thought it would have been nice if there lisp interpreter built on top of PHP so I could at least play around with lisp while still using a cheap shared hosting plan. About a month ago, I ran across [LiScript](http://www.reddit.com/r/programming/comments/1anljo/ive_just_published_my_first_opensource/) on reddit and it was worth trying to emulate something similar in PHP. pEigthP is the result. I wrote the bulk of the code off and on over the course of 2 weeks then let it sit on my computer for the following several weeks. I figure I should release it now before it sits longer. It is released under the *MIT License*

### How to use it
This is a brief tutorial of pEigthP, it is intended to only describe the things that are implemented in pEigthP. If you don't know the basics of lisp, there are probably better tutorials out there.

####Including pEigthP in PHP code
After including the pEigthP core language file, pEigthP is designed to be embedded in PHP using an output buffer filter. Below would be a full php file:
```
<?php
include('pEigthP.php');
ob_start_peigthp();
?>
(echo "Hello world\n")
(echo (+ 1 2))
<?
ob_end_peigthp();
?>
Output: "Hello world
3"
```

pEigthP can also be put in a separate file and included in PHP code, as long as the ob_start_peigthp and ob_end_peigthp functions are on each side of the include call:
```
<?php
include('pEigthP.php');
ob_start_peigthp();
include('file.p8p');
ob_end_peigthp();
?>
```
Remember, if you do it this way, then you will need to configure your webserver to not allow p8p files to be displayed if someone were to access the p8p file directly. In other words: make sure that http://www.yourserver.com/file.p8p does not show a random internet stranger the source code to your website.

All functions and global variables in PHP are accessible to pEigthP. 
```
<?php
include('pEigthP.php');

$a_var = 'World';
function greet($who){
  echo "Hello $who";
}
ob_start_peigthp();
?>
(greet a_var)
<? ob_end_peigthp(); ?>
Output: "Hello world"
```

pEigthP variables and functions can be exported back to the PHP global namespace.
<?php
include('pEigthP.php');
ob_start_peigthp();
?>
(def a_var "World")
(defn greet [who] 
  (echo "Hello " who))
(export a_var greet)
<? 
ob_end_peigthp(); 
$greet($a_var);
?>
Output: "Hello world"
```

####Syntax
The syntax of pEigthP is loosely based off clojure’s syntax.
*String literals: `"a string"` or `’a string’`
**\r, \n and \t are converted when placed within double quotes. \" can be used to embed a quote character in a double quoted string: `"a \"quoted\"\tstring"` == `'a "quoted"    string'`
**Interpolation is not supported: `"$something"` == `'$something'`
*Number literals: `1`, `2.0`, `3E4`
**All numbers are passed through doubleeval() so are handled as doubles internally
*Arrays: `[1 "two" ['three'] 4 ]`
**An actual PHP array, just without the commas
**Can also be created using the array function: `(array 1 2 3)` == `[1 2 3]`
*Hash: `{ 'key' 'value' 2 'value2' 'key3' [1 2 3] }`
**Same as a PHP array, but the keys are explicitly specified:  `{1 'test' 0 'a'}` == `['a' 'test']`
*Lisp lists: `(+ 1 2)`
**These are a subclass of PHP's ArrayObject 
**Example:
```
(if (== (+ 1 2) 3)
  'Pass'
  'Fail')
Output: 'Pass'
```

*Quoted lisp lists: `'(+ 1 2)`
**Example:
```
(if (== (lisp_eval (unquote '(+ 1 2))) 3)
  'Pass'
  'Fail')
Output: 'Pass'
```
*Syntax quoted lisp lists `(+ 1 2)
**Supports "~" unquote and "~@" unquote splice operators
```
(if (== (let [a '(1 2)] 
          `(~a ~@a)) 
        '('(1 2) 1 2))
  'Pass'
  'Fail')
Output: 'Pass'
```
*Comments
**Supports both "//" single line comments and "/* ... */" multiline comments
*PHP Object Interactions
**(new TestClass 'foo')
***Equivalent in PHP: `new TestClass('foo')`
**(TestClass::a_constant)
***Equivalent in PHP: `TestClass::a_constant`
**(TestClass::a_static_var) 
***Equivalent in PHP: `TestClass::$a_static_var`
**(TestClass::a_static_function)
***Equivalent in PHP: `TestClass::a_static_function()`
**(->a_regular_var (new TestClass))
***Equivalent in PHP: `$a = new TestClass(); $a->a_regular_var`
**(->a_regular_function (new TestClass) 'foo')
***Equivalent in PHP: `$a = new TestClass(); $a->a_regular_function('foo')`
**(->a_static_var (new TestClass))
***Equivalent in PHP: `$a = new TestClass(); $a::$a_static_var`

####Functions
While PHP has a lot of infix operators, pEigthP uses just the prefix equivalents. 
*Math operators: `+ - * / % mod == === != !== < > <= >='
**mod does the same thing as % but can be used in functions were % is the name of a parameter
```
(echo (+ (/ 4 2) (- 6 1)))
Output: 7
```
*Boolean operators: `&& || xor !`
**These operators will return the last evaluated parameter if true
**They will also short circuit, so it will not evaluate unnecessary parameters:
```
(echo (|| (! true) 'pass' (throw (new Exception 'fail_exception'))))
Output: 'pass'
```
*Functions and macros: `function macro #() recur add_reader`
**As you would expect, `function` and `macro` create functions and macros that are callable
```
((function [a] (echo a)) "Hello World")
Output: "Hello World"
```
**They support list descructuring and grouping. A passed array can be split into its component elements using [] and the & symbol can be used to roll any additional parameters into an array:
```
((function [p1 [p21 p22 & p2r] & pr] 
  (echo p1 p21 p22 p2r pr)) [1 2 3] [4 5 6] [7 8 9] [10])
Output: [1 2 3] 4 5 [6] [[7 8 9] [10]]
```
**They also support default parameter values, which are declared using the syntax "(name default)" in the parameter list
```
((function [(p1 "a") (p2 "b") (p3 "c")] (echo p1 p2 p3 "\n")))
((function [(p1 "a") (p2 "b") (p3 "c")] (echo p1 p2 p3 "\n")) "d" "e")
Output: abc
dec
```
**The #() reader macro is a quick way to write an anonymous function, where the optional parameter names are hardcoded to %0 (also set to %) %1 %2 %3 %4 and %5
```
(if (== (filter #(mod % 2) [1 2 3 4]) [1 3])
  'pass'
  'fail')
(if (== (#(+ %0 %1) 1 2) 3)
  'pass'
  'fail')
```
**`recur` will call the current function again with the passed new parameters allowing anonymous recursive functions. No effort was made for tail call optimization. 
```
(let [x 5
      fib (function [x] 
            (if (== x 0) 
              1
              (* x (recur (- x 1)))))]
  (echo "Fib of " x " is " (fib x)))
Output: Fib of 5 is 120
```
**`add_reader` can be used to add new reader macros to pEighthP. All of pEigthP's internal readers are built using this function. Below is the code used to build the #() reader macro:
```
(add_reader "#(" ")" (macro [ tokens ] 
                      `(function [ (%0 null) (%1 null) (%2 null) (%3 null) (%4 null) (%5 null)] 
                        (let [% %0]
                         (~@tokens))))) 
```

*Permanent variable assignment: `def defn defmacro`
**These will add a value, function, or macro to the global pEigthP namespace
**The destructuring, grouping and default features described for function and macro work with defn and defmacro
```
(def a "Hello World ")
(echo a)

(defn say_hello [who] (echo (. "Hello " who)))
(say_hello "World ") 

(defmacro swap [a b] `(~b ~a))
(swap "Hello World " echo)

(defn say_hello2 [(who "World")] (echo (. "Hello " who)))
(say_hello)

Output: "Hello World Hello World Hello World Hello World"
```
*Variable export: `export`
**While any PHP function or global variable is accessible to pEigthP, you will need to explicitly export pEigthP variables back into the PHP namespace.
**Export can take any number of parameters.
**pEightP functions are exported as anonymous function assignments, which means that a $ will need to be added to the front of the function name when they are used in PHP code:
```
<?php
include('pEigthP.php');
ob_start_peigthp();
?>
(def a_var "World")
(defn greet [who] 
  (echo "Hello " who))
(export a_var greet)
<? 
ob_end_peigthp(); 
$greet($a_var);
?>
Output: "Hello world"
```
*Temporary variable assignment: `let`
**Used to bind (or redefine) variables within a lexical context of the `let` form. 
**Any number of variables can be defined using let. Later variables can use previously defined variables in their definition. 
**List destructuring and grouping can be used
```
(let [a 1
      b (+ 1 1)
      [c d & e] [3 4 4]
      f (+ 1 (first e))]
  (echo a b c d f))
Output: 12345
```
*Control flow: `if try throw do unless`
**`if` can take 2 or 3 parameters. The first parameter is the test: if it evaluates to true (using PHP's definition of true) then the 2nd parameter is evaluated and returned, otherwise the 3rd parameter is evaluated and returned (or null if no 3rd parameter is passed). 
**`unless` is the opposite of `if`. When the first parameter evaluates to false, the 2nd parameter is evaluated and returned, otherwise the 3rd parameter is evaluated and returned (or null if no 3rd parameter is passed).
**`try` and `throw` allow exceptions to be thrown and caught. `try` supports a finally block:
```
(echo (try (+ 1 1)
        catch Exception e (+ 1 2))
Output: 2


(echo (try 
        (try (throw (new Exception 'fail')) 
          finally (throw (new Exception 'pass')))
        catch Exception f (->getMessage f)))
Output: 'pass'
```
**`do` takes multiple expressions, executes them, and returns the value of the last one.
```
(echo (do (+ 1 1)
          (echo "Hello ")
          (. " Wo" "rld")))
Outputs: Hello World
```
*Array accessors: `first second last aget nth rest next nnext take drop empty?
**`first` `second` and `last` all take an array and return the specified element from the array
**`aget` takes an array a key (or index) and returns the element at that specific index. Multiple keys can be passed to "drill down" into an array within an array. `nth` is an alias of `aget`
```
(echo (aget ['apple' 'banana' 'orange'] 1))
(echo (aget {'k1' 'apple' 
             'k2' 'banana' 
             'k3' 'orange'}
             'k2'))
(let [arr {'k1' ['grapefruit' 'apple' {'kk1' 'banana' 
                                       'kk2' 'orange'}] 
           'k2' 'misc'}]
  (echo (aget arr 'k1' 2 'kk1')))
Output: bananabananabanana
```
**`next` returns a list of everything except the first element or null if there are no elements to return
**`rest` returns a list of everything except the first element or [] if there are no elements to return
**`nnext` returns a list of everything except the first two elements or null if there are no elements to return
**`take` and `drop` take a number and an array and return a sliced array
***`(take 2 [3 4 5])` == `[3 4]`
***`(drop 2 [3 4 5])` == `[5]`
**`empty?` Takes one parameter and returns true if it is null or an empty list
*Array builders: `assoc conj`
***`assoc` makes a copy of a passed array and then the rest of the parameters are used to add additional keys and values on the copied array. *The passed array is unaffected.*
```
(var_dump (let [a {'k1' 'apple' 'k2' 'grape'}]
            (assoc a 'k1' 'orange' 'k3' 'banana')))
Outputs:
array(3) {
  ["k2"]=>
  string(5) "grape"
  ["k1"]=>
  string(6) "orange"
  ["k3"]=>
  string(6) "banana"
}            
```
***`conj` takes a copy of a passed array and then the rest of the parameters are appended to the returned array. *The passed array is unaffected.*
```
(echo (conj [1 2] 3 4))
Outputs: 1234
```
*Variable updates: `set! aset!`
**`set!` updates the value of a PHP or pEigthP binding.
```
(def b "Hello")
(set! b (. b " world"))
(echo b)
Outputs: Hello world
```
**`set!` can also be used to update the value of a PHP object's field
```
(def an_object (new TestClass))
(set! ->a_regular_var an_object 3)
(echo (->a_regular_var an_object))
Outputs: 3
```
**`aset!` can update a element in an array.
```
(def a ['x' 'y' 'z'])
(aset! a 1 'w')
(echo a)
Outputs: [xwz]
```
**`aset!` can be passed multiple keys to update a element in a sub array
(def a {'k1' ['grapefruit' 'apple' {'kk1' 'banana' 
                                    'kk2' 'orange'}] 
        'k2' 'misc'})
(aset! a 'k1' 2 'kk1' 'pineapple')
(var_dump a)
Outputs:array(2) {
  ["k2"]=>
  string(4) "misc"
  ["k1"]=>
  array(3) {
    [0]=>
    string(10) "grapefruit"
    [1]=>
    string(5) "apple"
    [2]=>
    array(2) {
      ["kk2"]=>
      string(6) "orange"
      ["kk1"]=>
      string(9) "pineapple"
    }
  }
}
```
*Iteration: `foreach map filter partition reduce`
**`map` takes a function and an array and returns a new array with the function applied to each element. If the function passed to `map` can take 2 parameters, the 2nd parameter is the element's index. `foreach` is an alias to `map`
```
(echo (map #(* %0 2) [1 2 3]))
(map #(echo (. "\nKey: " %1 " Value: " %0)) {'k1' 'v1' 'k2' 'v2'})
Outputs: [246]
Key: k2 Value: v2
Key: k1 Value: v1
```
**`filter` takes a function and an array and returns a new array with only the elements where the passed function returns true. 
```
(echo (filter #(mod % 2) [1 2 3 4]))
Outputs: [13]
```
**If the function passed to `filter` can take 2 parameters, the 2nd parameter is the element's index.
```
(var_dump (filter #(|| (preg_match "/aa/" %0) (== "k2" %1)) {'k1' 'apple' 
                                                             'k2' 'ant' 
                                                             'k3' 'aardvark'}))
Outputs: array(2) {
  ["k3"]=>
  string(8) "aardvark"
  ["k2"]=>
  string(3) "ant"
}
```
**`partition` takes a number n and an array and will split the array into an array of arrays of n elements each. If the array does not fit evenly into arrays of n elements, the remaining elements will be dropped. `(partition 2 [1 2 3 4 5])` == '[[1 2][3 4]]`
**`reduce` takes a function f to process two args, an initial accumulator value, and an array. f is called on each element of the array with the accumulator value and the array element; the returned value becomes the new accumulator value. `reduce` returns the final value of the accumulator. If no accumulator value is passed, the first element of the array is used.
```
(echo (reduce #(+ %0 (* 2 %1)) 1 [2 3 4]))
(echo (reduce + [1 2 3]))
Outputs: 19 6
```
*Namespace: `use`
**`use` was quickly added so I could emulate Google's PHP app engine [tutorials](https://developers.google.com/appengine/docs/php/gettingstarted/usingusers). It works, but is relatively untested. It SHOULD work similar to use in PHP.
```
(use google\appengine\api\users\UserService)
(use google\appengine\api\users\UserService USrv)
(def user (UserService::getCurrentUser))
(def user2 (USrv::getCurrentUser))
```

*Assert: `lisp_assert`
**`lisp_assert` takes a string and a value. The string is evaluated as lisp code compared to the value. If they are the same, nothing happens. If they are different, an exception is thrown. tests.p8p has a set of tests that were built up while I developed pEigthP.
```
(lisp_assert "(partition 2 [1 2 3 4 5])" [[1 2][3 4]])
``` 

####Lexical closures
When a function is defined, a lexical closure is made, which can then be used. Mutable variables that are modified are changed for all functions that share the same lexical closure. 
```
(let [a 0]
  (defn incrementer []
    (set! a (+ a 1)))
  (defn decrementer []
    (set! a (- a 1)))))
(echo (incrementer) (incrementer) (decrementer))
Outputs: 121
```
A different lexical enviroment is called each time a function is called:
```
(defn build_inc_and_dec []
  (let [a 0]
    [#(set! a (+ a 1)) #(set! a (- a 1))]))
(let [[inc1 dec1] (build_inc_and_dec)
      [inc2 dec2] (build_inc_and_dec)]
  (echo (inc1) (inc1) (inc2) (dec1)))
Outputs: 1211
```
