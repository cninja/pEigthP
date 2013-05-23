<?php
/*
 Copyright (C) 2013 Peter Siewert

 Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

 The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE. */
/******* Core Classes *********/
class LISP_OBJECT  {
  private $value = NULL;
  public function __construct($value) {
    $this->value = $value;
  }
  public function __toString()
  {
      return get_class($this);
  }
  public function value(){
    return $this->value;
  }
  public function setValue($value){
    return $this->value = $value;
  }
}
abstract class LISP_ARRAY extends ArrayObject {
  abstract public function do_eval();
  public function __construct($value = array()){
    if(is_a($value, "LISP_ARRAY")){
      $value = lisp_as_array($value);
    }
    parent::__construct($value);
  }
}
$lisp_function_stack = array();
class LISP_LIST extends LISP_ARRAY {
  public function __toString()
  {
      return "( ... )";
  }
  public function as_array(){
    return lisp_as_array($this);
  }
  public function do_eval($in_reader = false){
    global $lisp_function_stack;
    array_unshift($lisp_function_stack, lisp_as_string($this[0]));
    $first = lisp_eval($this[0]);
    $tail = array_slice(lisp_as_array($this), 1);
    if(is_a($first, "MACRO")){
      if($in_reader){
        $ans = $first->do_macro($tail);
        array_shift($lisp_function_stack);
        return $ans;
      }else{
        $ans = lisp_eval($first->do_macro($tail));
        array_shift($lisp_function_stack);
        return $ans;
      }
    }else if(is_a($first, 'NO_PARAM_EVAL')){
      $ans = call_user_func_array($first->value(), $tail);
      array_shift($lisp_function_stack);
      return $ans;
    }else{
      $resolved_tail = array();
      foreach($tail as $param){
        $resolved_tail[] =  is_a($param, "LISP_ARRAY") ? $param->do_eval() : lisp_eval($param);
      }
      
      //$GLOBALS['lisp_function_stack'] = lisp_as_string($this[0]);
      if(is_a($first, 'OBJECT_FUNCTION')){
        $object = array_shift($resolved_tail);
        //var_dump($object, $first, $resolved_tail);
        if(property_exists($object, $first->value())){
          $rp = new ReflectionProperty($object, $first->value());
          $ans = $rp->getValue($object);
          array_shift($lisp_function_stack);
          return $ans;
        }else{
          $ans = call_user_func_array(array($object, $first->value()), $resolved_tail);
          array_shift($lisp_function_stack);
          return $ans;
        }
      }else if(is_a($first, 'STATIC_OBJECT_FUNCTION')){
        list($object, $param) = explode("::", $first->value());
        $rc = new ReflectionClass($object);
        if($rc->hasProperty($param)){
          $ans = $rc->getStaticPropertyValue($param);
          array_shift($lisp_function_stack);
          return $ans;
        }else if($rc->hasConstant($param)){
          $ans = $rc->getConstant($param);
          array_shift($lisp_function_stack);
          return $ans;
        }else{
          $ans = call_user_func_array(array($object, $param), $resolved_tail);
          array_shift($lisp_function_stack);
          return $ans;
        }
      }else{
        if(is_callable($first)){
          $ans = call_user_func_array($first, $resolved_tail);
          array_shift($lisp_function_stack);
          return $ans;
        }else{
          //var_dump($first);
          throw new Exception("Cannot call " . $first . " as a function at or around " . join($lisp_function_stack, ' :: '));
        }
      }
    }    
  }
  public function macroexpand(){
    global $lisp_function_stack;
    array_unshift($lisp_function_stack, lisp_as_string($this[0]));
    
    if(is_a($this[0], 'SYMBOL') && is_a(resolve_symbol($this[0], false, true), 'MACRO')){
      $first = resolve_symbol($this[0]);
      $tail = array_slice(lisp_as_array($this), 1);
      $ans = $first->do_macro($tail);
      array_shift($lisp_function_stack);
      return $ans;
    }else{
      array_shift($lisp_function_stack);
      return $this;
    }
  }
}
class QUOTED_LISP_LIST extends LISP_ARRAY {
  public function __toString()
  {
      return "'( ... )";
  }
  public function do_eval(){ 
    return $this; 
  }
}
class SYNTAX_QUOTED_LISP_LIST extends QUOTED_LISP_LIST{
  public function do_eval(){
    $unquote = function($tokens, $uniq_tokens = array()) use (&$unquote){
 
                $new_tokens = lisp_new_for_type($tokens);
                $k_invalid = false;
                foreach($tokens as $k => $token){
                  if(is_a($token, "SYMBOL") && substr( lisp_as_string($token), 0, 2) == "~@"){
                    foreach($unquote(resolve_symbol($token, true)) as $token_item){
                      $new_tokens[] = $token_item;
                    }
                    $k_invalid = true;
                  }else{
                    if(is_array($token) || is_a($token, "LISP_ARRAY")){
                      $value = $unquote($token);
                    }else if(is_a($token, "SYMBOL") && substr( lisp_as_string($token), 0, 1) == "~"){
                      $value = resolve_symbol($token, true);
                    }else{
                      $value = $token;
                    }
                    if($k_invalid){
                      $new_tokens[] = $value;
                    }else{
                      $new_tokens[$k] = $value;
                    }
                  }
                }
                return $new_tokens;
              };
    return new QUOTED_LISP_LIST( $unquote($this));
  }
}

class MACRO extends LISP_OBJECT {
  public function do_macro($tail){
    $ans = call_user_func_array($this->value(), $tail);
    if(is_a($ans, "QUOTED_LISP_LIST")){
      $ans = new LISP_LIST($ans);
    }
    
    if(is_a($ans, "LISP_LIST")){
      return $ans->macroexpand();
    }else{
      return $ans;
    }
  }
}
class NO_PARAM_EVAL extends LISP_OBJECT {}
class OBJECT_FUNCTION extends LISP_OBJECT {}
class STATIC_OBJECT_FUNCTION extends LISP_OBJECT {}

class SYMBOL extends LISP_OBJECT {
  public static function forToken($token, $no_sub_reader = false){
    if(is_numeric($token) && !$no_sub_reader){
      return doubleval($token);
    }else{
      return new SYMBOL($token);
    }
  }
}

/******Parser*********/
$lisp_reader_char_counts = array();
$lisp_readers = array();
function add_reader($begin_token, $end_token, $compiler, $escape_chr = null, $no_sub_reader = false){
  global $lisp_reader_char_counts, $lisp_readers;

  $lisp_readers[strlen($begin_token)][$begin_token] = array('begin' => $begin_token, 'close' => $end_token, 'compiler' => $compiler, 'escape_chr' => $escape_chr, 'no_sub_reader' => $no_sub_reader);
  
  $new_lisp_reader_char_counts = array_keys($lisp_readers);
  rsort($new_lisp_reader_char_counts);
  $lisp_reader_char_counts = $new_lisp_reader_char_counts;
}
function lisp_match_reader($str){
  global $lisp_reader_char_counts, $lisp_readers;

  foreach($lisp_reader_char_counts as $count){
    if(array_key_exists(substr($str, 0, $count), $lisp_readers[$count])){
      return array(substr($str, $count), $lisp_readers[$count][substr($str, 0, $count)]);
    }
  }
  return array($str, null);
}

function lisp_parse_string($str, $eval_fn = null){
  $ans = array();
  $str = trim($str);
  
  while($str != ""){
    $reader_found = false;
    $tree = null;
    list($str, $reader) = lisp_match_reader($str);
    
    if(!$reader){
      throw new Exception("I dont know how to parse \"" . substr($str, 0, 30) ."\"\n");
    }
    list($str, $tree) = lisp_parse_string_with_reader($str, $reader);
    $str = trim($str);
    if($eval_fn){
      $ans = call_user_func($eval_fn, $tree);
    }else{
      $ans[] = $tree;
    }
  }
  return $ans;
}

function lisp_parse_string_with_reader($str, $reader){
  $orig_str = $str;
  $tokens = array();
  $current_token = null;
  $last_chr = null;
  $reader_closed_successfully = false;

  for($i = 0; $i < strlen($str); $i++){
    if(substr($str, $i, strlen($reader['close'])) == $reader['close']){
      if(!$reader['escape_chr'] || $last_chr != $reader['escape_chr']){
        $i += strlen($reader['close']);
        $reader_closed_successfully = true;
        break;
      }else{
        //Remove escape chr and add reader[close] token
        $current_token = substr($current_token, 0, strlen($current_token) - 1) . substr($str, $i, strlen($reader['close']));
        $i += strlen($reader['close']);
        $last_chr = null;
        
        continue;
      }
    }else{
      $current_chr = $str[$i];
      if(!$reader['no_sub_reader']){
        list($sub_str, $sub_reader) = lisp_match_reader(substr($str, $i));
        if($sub_reader){
          if(isset($current_token)){
            $tokens[] = SYMBOL::forToken($current_token);
          }
          
          list($str, $tree) = lisp_parse_string_with_reader($sub_str, $sub_reader);
          $i = -1;
          $tokens[] = $tree;
          $current_token = null;
          $last_chr = null;
          continue;
        }
      }
      if(!$reader['no_sub_reader'] && preg_match("/\s/", $current_chr)){
        if(isset($current_token)){
          $tokens[] = SYMBOL::forToken($current_token);
        }
        $current_token = null;
        $last_chr = null;
      }else if($current_chr == $reader['escape_chr'] && $last_chr == $reader['escape_chr']){
        $last_chr = null;
      }else{
        $current_token .= $current_chr;
        $last_chr = $current_chr;
      }
      
    }
  }
  
  if(!$reader_closed_successfully && !preg_match("/\s/", $reader['close'])){
    throw new Exception("Unexpected end of input: missing a " . $reader['close'] . " when reading the form \"" . trim(substr($orig_str, 0, 30)) . "\"");
  }
  if(isset($current_token)){
    $tokens[] = SYMBOL::forToken($current_token, $reader['no_sub_reader']);
  }

  if(is_callable($reader['compiler'])){
    return array(substr($str, $i), call_user_func($reader['compiler'], $tokens));
  }else{
    $ast = new LISP_LIST(array($reader['compiler'], $tokens));
    return array(substr($str, $i), $ast->do_eval(true));
  }
}

function lisp_eval_string($lisp_code){
  return lisp_parse_string($lisp_code, "lisp_eval");
}


/******** Core peigthp ************/
$lisp_bindings = array();
function push_lisp_bindings(&$new_bindings) {
  global $lisp_bindings;
  
  $lisp_bindings[] = &$new_bindings;
  //echo " push" . count($lisp_bindings);
}
function pop_lisp_bindings(){
  global $lisp_bindings;

  //echo " pop" . count($lisp_bindings);
  array_pop($lisp_bindings);
}

function set_symbol($sym, $new_val, $obj_val = null){
  global $lisp_bindings;

  for($i = count($lisp_bindings) - 1; $i >= 0; $i--){
    if(array_key_exists($sym->value(), $lisp_bindings[$i])){
      $lisp_bindings[$i][$sym->value()] = lisp_eval($new_val);
      return $lisp_bindings[$i][$sym->value()];
    }
  }
  
  if(array_key_exists($sym->value(), $GLOBALS)){
    $GLOBALS[$sym->value()] = lisp_eval($new_val);
    return $GLOBALS[$sym->value()];
  }else if($sym->value() && substr("".$sym->value(), 0, 2) == "->" && func_num_args() == 3){
    $obj = resolve_symbol($new_val);
    $obj->{substr("".$sym->value(), 2)} = lisp_eval($obj_val);
    return $obj->{substr("".$sym->value(), 2)};
  }else{
    //debug_print_backtrace();
    throw new Exception("Unknown symbol: " . $sym->value() ." in or around " . join( $GLOBALS['lisp_function_stack'], ' :: '));
  }
}
$lisp_use_aliases = [];
function resolve_symbol($sym, $do_unquote = false, $skip_exception = false){
  global $lisp_bindings, $lisp_use_aliases;
  if(!is_a($sym, 'SYMBOL')){
    throw new Exception($sym . " is not a symbol");
  }
  $value = $sym->value();
  if($do_unquote){
    $value = preg_replace("/^~@|^~/", "", $value);
  }

  for($i = count($lisp_bindings) - 1; $i >= 0; $i--){
    if(array_key_exists($value, $lisp_bindings[$i])){
      return $lisp_bindings[$i][$value];
    }
  }
  
  foreach($lisp_use_aliases as $alias => $fullname){
    if($value == $alias || preg_match("/^" . $alias ."\b/", $value)){
      $value = preg_replace("/^" . $alias . "/", $fullname, $value);
      break;
    }
  }

  //Try PHP scope
  if(array_key_exists($value, $GLOBALS)){
    return $GLOBALS[$value];
  }else if(is_callable($value)){
    return $value;
  }else if(class_exists($value)){
    return $value;
  }else if($value && substr($value, 0, 2) == "->"){
    return new OBJECT_FUNCTION(substr($value, 2));
  }else if($value && strpos($value, "::") !== false){
    return new STATIC_OBJECT_FUNCTION($value);
  }else if(defined($value)){
    return constant($value);
  }else if($skip_exception){
    return null;
  }else{
    //var_dump($lisp_bindings);
    //var_dump(array_slice($lisp_bindings, 0, count($lisp_bindings) - 1));
    throw new Exception("Unknown symbol: " . $value." in or around " . join($GLOBALS['lisp_function_stack'], ' :: '));
  }
  
  
}

function lisp_as_string($a){
  if(is_a($a, 'SYMBOL')){
    return $a->value();
  }else{
    return $a;
  }
}
function lisp_as_array($a){
  return (array) $a;
}
function lisp_new_for_type($a){
  if(is_array($a)){
    return array();
  }
  return (new ReflectionClass(get_class($a)))->newInstance();
}

function lisp_eval($a){
  if(is_a($a, 'LISP_ARRAY')){
    return $a->do_eval();
  }else if(is_a($a, 'SYMBOL')){
    return resolve_symbol($a);
  }else if(is_array($a)){
    $new_a = array();
    foreach($a as $k=>$v){
      $new_a[$k] = lisp_eval($v);
    }
    return $new_a;
  }else{
    return $a;
  }
}

function ob_start_peigthp(){
  ob_start();
}
function ob_end_peigthp(){
  $lisp_code = ob_get_contents();
  ob_end_clean();
  lisp_eval_string($lisp_code);
}
function lisp_bind_arguments($arg_symbols, $func_args, $arg_bindings = array()){
  $rest_of_args_values = array();
  $rest_of_args_name = null; 
  
  for($i = 0; $i < count($func_args) && ($i < count($arg_symbols) || isset($rest_of_args_name)); $i++){
    if(isset($rest_of_args_name)){
      $rest_of_args_values[] = $func_args[$i];
    }else if($arg_symbols[$i] == Symbol::forToken("&")){
      if(count($arg_symbols) == $i + 1){
        throw new Exception("Function " . join($GLOBALS['lisp_function_stack'], ' :: ') . " has no parameter after the &");
      }
      $rest_of_args_name = lisp_as_string($arg_symbols[$i + 1]);                  
      $rest_of_args_values[] = $func_args[$i];
    }else{
      if(is_a($arg_symbols[$i], 'LISP_LIST')){
        $symbol = lisp_as_string($arg_symbols[$i][0]);
        $value = $func_args[$i];
        if(!isset($value)){
          $value = lisp_eval($arg_symbols[$i][1]);
        }
        $arg_bindings[$symbol] = $value;
      }else if(is_array($arg_symbols[$i])){
        $arg_bindings = lisp_bind_arguments($arg_symbols[$i], $func_args[$i], $arg_bindings);
      }else{
        $arg_bindings[lisp_as_string($arg_symbols[$i])] = $func_args[$i];
      }
    }
  }
  if(count($arg_symbols) > $i && !isset($rest_of_args_name)){
    for(; $i < count($arg_symbols); $i++){
      if($arg_symbols[$i] == Symbol::forToken("&")){
        if(count($arg_symbols) == $i + 1){
          throw new Exception("Function " . join($GLOBALS['lisp_function_stack'], ' :: ') . " has no parameter after the &");
        }
        $rest_of_args_name = lisp_as_string($arg_symbols[$i + 1]);
        break;             
      }else{
        if(is_a($arg_symbols[$i], 'LISP_LIST')){
          $symbol = lisp_as_string($arg_symbols[$i][0]);
          $value = lisp_eval($arg_symbols[$i][1]);
          $arg_bindings[$symbol] = $value;
        }else{
          //var_dump($i, $arg_symbols, $arg_bindings);
          throw new Exception("Function " . join($GLOBALS['lisp_function_stack'], ' :: ') . " was called with " . func_num_args() . " arguments but " . count($arg_symbols) . " were expected");
        }
      }
    }
  }
  if(isset($rest_of_args_name)){
    $arg_bindings[$rest_of_args_name] = $rest_of_args_values;
  }
  return $arg_bindings;
}
function lisp_build_function($args, $body, $unquote_body = false){
  global $lisp_bindings;
  
  $arg_symbols = lisp_as_array($args);
  if($unquote_body && is_a($body, 'QUOTED_LISP_LIST')){
    $body = new LISP_LIST($body);
  }

  $env = array();
  foreach($lisp_bindings as $k=>$v){
    $env[$k] = &$lisp_bindings[$k];
  }

  $fn = function() use ($arg_symbols, $body, &$env, &$fn){
    global $lisp_bindings;
    
    $arg_bindings = lisp_bind_arguments($arg_symbols, func_get_args());
    
    
    //Basic recursion
    $arg_bindings['recur'] = $fn;
    
    //Swap bindings for function closure
    $old_lisp_bindings = $lisp_bindings;
    $lisp_bindings = $env;
    push_lisp_bindings($arg_bindings);
    try{
      $ans = is_a($body, "LISP_ARRAY") ? $body->do_eval() : lisp_eval($body);
    }catch(Exception $e){
      pop_lisp_bindings();
      $env = $lisp_bindings;
      $lisp_bindings = $old_lisp_bindings;      
      throw $e;
    }
    pop_lisp_bindings();
    $env = $lisp_bindings;
    $lisp_bindings = $old_lisp_bindings;      
    return $ans;
  };
  return $fn;
}

function reduce_args($a, $fn){
  $b = array_shift($a); 
  return array_reduce($a, $fn, $b);
}
function reduce_bool_args($a, $fn, $start_bool, $return_last_val = false){
  if($return_last_val){
    $b = $start_bool;
  }else{
    $b = lisp_eval(array_shift($a));
  }
  $c = $b;

  foreach($a as $pre_evaled_c){
    $c = lisp_eval($pre_evaled_c);
    if($fn($b, $c) !== $start_bool){
      if($return_last_val && !$start_bool){
        return $b ? $b : $c;
      }else{
        return !$start_bool;
      }
    }
    $b = $c;
  }
  
  if($return_last_val){
    return $c;
  }else{
    return $start_bool;
  }
}

$lisp_bindings = array(array(
  //Load PHP infix operators and language constructs
  '+' => function(){ return reduce_args(func_get_args(), function($a, $b){return $a + $b; }); },
  '-' => function(){ return reduce_args(func_get_args(), function($a, $b){return $a - $b; }); },
  '*' => function(){ return reduce_args(func_get_args(), function($a, $b){return $a * $b; }); },
  '/' => function(){ return reduce_args(func_get_args(), function($a, $b){return $a / $b; }); },
  '%' => function(){ return reduce_args(func_get_args(), function($a, $b){return $a % $b; }); },
  '==' => new NO_PARAM_EVAL(function(){ return reduce_bool_args(func_get_args(), function($c, $d){return $c == $d; }, true);}),
  '===' => new NO_PARAM_EVAL(function(){ return reduce_bool_args(func_get_args(), function($c, $d){return $c === $d; }, true);}),
  '!=' => new NO_PARAM_EVAL(function(){ return reduce_bool_args(func_get_args(), function($c, $d){return $c != $d; }, false);}),
  '!==' => new NO_PARAM_EVAL(function(){ return reduce_bool_args(func_get_args(), function($c, $d){return $c !== $d; }, false);}),
  '<' => new NO_PARAM_EVAL(function(){ return reduce_bool_args(func_get_args(), function($c, $d){return $c < $d; }, true);}),
  '>' => new NO_PARAM_EVAL(function(){ return reduce_bool_args(func_get_args(), function($c, $d){return $c > $d; }, true);}),
  '<=' => new NO_PARAM_EVAL(function(){ return reduce_bool_args(func_get_args(), function($c, $d){return $c <= $d; }, true);}),
  '>=' => new NO_PARAM_EVAL(function(){ return reduce_bool_args(func_get_args(), function($c, $d){return $c >= $d; }, true);}),
  '&&' => new NO_PARAM_EVAL(function(){ return reduce_bool_args(func_get_args(), function($c, $d){return $c && $d; }, true, true);}),
  '||' => new NO_PARAM_EVAL(function(){ return reduce_bool_args(func_get_args(), function($c, $d){return $c || $d; }, false, true);}),
  'xor' => function($a, $b){ return $a xor $b; },
  '!' => function($a){return ! $a; },
  'null' => null,
  'true' => true,
  'false' => false,
  'new' => function($a){
              if(func_num_args() > 1){ 
                return (new ReflectionClass($a))->newInstanceArgs(array_slice(func_get_args(), 1));
              }else{
                return (new ReflectionClass($a))->newInstance();
              }
           },
  '.' => function(){ $a = func_get_args(); $b = array_shift($a); return array_reduce($a, function($c, $d){return $c . $d; }, $b);},
  'echo' => function(){ $do_echo = function($items) use (&$do_echo){
                          $b = null;
                          foreach($items as $a){ 
                            if(is_array($a)){
                              echo "[";
                              call_user_func($do_echo, $a);
                              echo "]";
                            }else{
                              echo $a;
                            }
                            $b = $a; 
                          } 
                          return $b;
                        };
                        return call_user_func($do_echo, func_get_args());
                      },
  'array' => function(){ return func_get_args(); },
  'foreach' => function($fn, $a){ $new_a = lisp_new_for_type($a); foreach($a as $k => $v){ $new_a[$k] = $fn($v, $k);} return $new_a; },
  'while' => function($a, $b){
                $ans = null;
                while($a()){
                  $ans = $b();
                }
                return $ans;
             },
  'throw' => function($a){ throw $a; },
  'if' => new NO_PARAM_EVAL(function($cond, $t, $f=null){ 
            if( lisp_eval($cond)){
              return lisp_eval($t);
            }else{
              return lisp_eval($f);
            }
          }),
  'try' => new NO_PARAM_EVAL(function(){
              $ans = null;
              $exception_to_throw = null;
              $try_blocks = array();
              $catch_blocks = array();
              $finally_blocks = array();
              $state = 0;
              foreach(func_get_args() as $block){
                if($state == 0){
                  if($block == Symbol::forToken("catch")){
                    $state = 1;
                  }else if($block == Symbol::forToken("finally")){
                    $state = 2;
                  }else{
                    $try_blocks[] = $block;
                  }
                }else if($state == 1){
                  if($block == Symbol::forToken("finally")){
                    $state = 2;
                  }else{
                    $catch_blocks[] = $block;
                  }
                }else{
                  $finally_blocks[] = $block;
                }
              }
              try {
                foreach($try_blocks as $block){ 
                  $ans = lisp_eval($block); 
                }
              } catch (Exception $e){
                for($i = 0; $i < count($catch_blocks); $i += 3){
                  if(is_a($e, lisp_eval($catch_blocks[$i]))){
                    $catch_block_bindings = array(lisp_as_string($catch_blocks[$i + 1])=> $e);
                    push_lisp_bindings($catch_block_bindings);
                    try{
                      $ans = lisp_eval($catch_blocks[$i + 2]);
                    }catch(Exception $f){
                      echo $f->getMessage();
                      $exception_to_throw = $f;
                    }
                    
                    pop_lisp_bindings();
                    $e = null;
                    break;
                  }
                }
                if(!$exception_to_throw){
                  $exception_to_throw = $e;
                }
              }
              
              foreach($finally_blocks as $block){
                try{
                  lisp_eval($block);
                }catch(Exception $f){
                  
                  $exception_to_throw = $f;
                }
              }

              if($exception_to_throw){
                throw $exception_to_throw;
              }
              return $ans;
            }),
  
/************Core functions************************/
  
  'def' => new NO_PARAM_EVAL(function($name, $body){
            global $lisp_bindings;
            $lisp_bindings[0][lisp_as_string($name)] = lisp_eval($body);
          }),
  'export' => new NO_PARAM_EVAL(function(){
            for($i = 0; $i < func_num_args(); $i++){

                $GLOBALS[lisp_as_string(func_get_arg($i))] = lisp_eval(func_get_arg($i));
            }
          }),
  'macro' => new NO_PARAM_EVAL(function($args, $body){ return new MACRO( lisp_build_function($args, $body) ); }),           
  'function' => new NO_PARAM_EVAL(function($args, $body){ return lisp_build_function($args, $body); }),
  'set!' => new NO_PARAM_EVAL(function($a, $b){ return call_user_func_array("set_symbol", func_get_args());}),
  'assoc' => function($a, $k, $v){ 
              //Array copy
              $b = $a; 
              for($i = 1; $i < func_num_args(); $i += 2){
                $b[func_get_arg($i)] = func_get_arg($i + 1); 
              }
              return $b;
           },
  'conj' => function($a){
              //Array copy
              $b = $a;
              for($i = 1; $i < func_num_args(); $i++){
                $b[] = func_get_arg($i); 
              }
              return $b;
            },         
  'aget' => function($a, $k){ 
              $i = 1;
              while($i < func_num_args()){
                $a = $a[func_get_arg($i)];
                $i++;
              }
              return $a; },
  'aset!' => new NO_PARAM_EVAL(function($a_symbol, $k, $v){
              $a = lisp_eval($a_symbol);
              $b = &$a;
              $i = 1;
              while($i < func_num_args() - 2){
                $a =& $a[lisp_eval(func_get_arg($i))];
                $i++;
              }
              $a[lisp_eval(func_get_arg($i))] = lisp_eval(func_get_arg($i + 1)); 
              if(is_a($a_symbol, "SYMBOL")){
                return set_symbol($a_symbol, $b);
              }else{
                return $b;
              }}),
  'let' => new NO_PARAM_EVAL(function($bindings){
              $evaled_bindings = array();
              push_lisp_bindings($evaled_bindings);
              
              try{
                if(is_a($bindings, "LISP_LIST")){
                  $bindings = lisp_eval($bindings);
                }
                for($i = 0; $i < count($bindings); $i += 2){
                  if(is_a($bindings[$i], "SYMBOL")){
                    $evaled_bindings[lisp_as_string($bindings[$i])] = lisp_eval($bindings[$i+1]);
                  }else if(is_array($bindings[$i])){
                    foreach(lisp_bind_arguments($bindings[$i], lisp_eval($bindings[$i+1])) as $sym => $val){
                      $evaled_bindings[$sym] = $val;
                    }
                  }else{
                    throw new Exception("Unknown let symbol: " . $bindings[$i]);
                  }
                }
                $ans = NULL;
                for($i = 1; $i < func_num_args(); $i++){
                  $ans = lisp_eval(func_get_arg($i));
                }
              }catch(Exception $e){
                pop_lisp_bindings();
                throw $e;
              }
              pop_lisp_bindings();
              return $ans;
            })   
));


add_reader("(", ")", function($symbols){ return new LISP_LIST($symbols); });                   
add_reader('"', '"', function($symbols){ if(count($symbols) == 0) return "";
                                         if(!is_a($symbols[0], "SYMBOL")) return $symbols[0];
                                         return str_replace(array("\\r", "\\n", "\\t"),
                                                                array("\r", "\n", "\t"),
                                                                $symbols[0]->value());
                                       }, '\\', true);

ob_start_peigthp();
?>

(add_reader "[" "]" (function (tokens) (lisp_as_array tokens)))

(add_reader "//" "\n" (function [] null) null true)
(add_reader "/*" "*/" (function [] null) null true)                                      
(add_reader "'(" ")" (function [tokens] (new QUOTED_LISP_LIST (lisp_as_array tokens))))
(add_reader "`(" ")" (function [tokens] (new SYNTAX_QUOTED_LISP_LIST (lisp_as_array tokens))))
(add_reader "#(" ")" (macro [ tokens ] 
                      `(function [ (%0 null) (%1 null) (%2 null) (%3 null) (%4 null) (%5 null)] 
                        (let [% %0]
                         (~@tokens))))) 

(def defn (macro [name args body] `(def ~name (function ~args ~body))))
(def defmacro (macro [name args body] `(def ~name (macro ~args ~body))))
(def map foreach)                 
(def first #(aget % 0))
(def second #(aget % 1))
(def rest #(array_slice % 1))
(def next #(if (<= 1 (count %)) (rest %) null))
(def nnext #(next (rest %)))
(defn take [n a] (array_slice a 0 n))
(defn drop [n a] (array_slice a n))
(def empty? #(== 0 (count %)))
(def last #(aget % (- (count %) 1)))
(defn identity [a] a)
(def eval lisp_eval)
(def mod %)
(def nth aget)
(defmacro unless [test then (else null)] `(if (! ~test) ~then ~else))
(defmacro if-let [[bind expr] then (else null)] `(let [~bind ~expr] (if ~bind ~then ~else)))
(defmacro if-null [expr alternate-expr] `(if-let [value# ~expr] value# ~alternate-expr))

(defn do [ & exprs ] (last (foreach identity exprs)))  
(defn filter [fn a ]
  (let [ans (lisp_new_for_type a)]
    (map #(if (fn %0 %1) 
            (set! ans 
              (if (is_int %1) 
                (conj ans %0) 
                (assoc ans %1 %0)))) a)
    ans))
    

(defn reduce [f & val_coll]
  (if (== (count val_coll) 1)
    (recur f (first (first val_coll)) (rest (first val_coll)))
    (let [[val coll] val_coll]
      (if (empty? coll)
        val
        (recur f (f val (first coll)) (rest coll))))))
(defn partition [n a] 
  (array_reverse (#(if (< (count %) n) [] (conj (recur (drop n %)) (take n %))) a)))

(add_reader "'" "'" (function [tokens] (if (== 0 (count tokens)) "" (->value (aget tokens 0)))) "\\" true)
(add_reader "{" "}" (function (tokens) (let [a []]
                                         (map #(aset! a (first %) (second %)) (partition 2 tokens))
                                         a)))
(defn unquote (a) (new LISP_LIST a))                                

(defn lisp_assert [test val] 
              (let [result (if (is_string test) 
                               (try (lisp_eval_string test) 
                                 catch Exception e (throw (new Exception (. "Assert failed for " test "! Expected " val " but got the exception message: " (->getMessage e)))))
                                 test)]
                (if (!= val result) 
                    (throw (new Exception (. "Assert failed for " test "! Expected " val " but got " result))))))
                
(defn use [name (alias null)]
  (aset! lisp_use_aliases (if-null alias (last (explode "\\" name))) name))

(defn repeat [n f]
  (while #(set! n (- n 1)) f))
    
<?
ob_end_peigthp();
?>