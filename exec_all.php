<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("detect_all_userinput.php");
include("js_endpoint_fuzzer.php");
include("js_secrets_fuzzer.php");
include("param_crawl_fuzz.php");

$runner = $_POST['selected-options'];
$runall = $_POST['all-selected'];

if (isset($runall) && $runall == "Y" || $runall) {
  runall(); // function to be implemented
}

if (isset($runner) {

    // to do 
}

?>
