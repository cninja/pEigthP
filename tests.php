<?php
error_reporting(E_ALL);
include('pEigthP.php');

class TestClass {
  const a_constant = "Hi const";
  public static $a_static_var = "Hi Static";
  public $a_regular_var = "Hi Regular";
  public function a_regular_function($param1){
    return "Passed $param1";
  }
  public static function a_static_function(){
    return "A static func";
  }
}
function add_one($param = 1){
  return $param + 1;
}

ob_start_peigthp();

include('tests.p8p');

?>

(echo "Tests passed!")

<?
ob_end_peigthp();
?>