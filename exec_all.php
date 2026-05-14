<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once("detect_all_userinput.php");
require_once("js_endpoint_fuzzer.php");
require_once("js_secrets_fuzzer.php");
require_once("param_crawl_fuzz.php");
require_once("run_internal_recon_tools.php");
require_once("dom_xss_sources_sinks.php");
require_once("js_deob_unminify_beautify.php");

$runner = $_POST['selected-options'];
$runall = $_POST['all-selected'];

public function runAll() {
    echo "<br /><p>ROOT ACCESS REQUIRED</p>";
    system("cd sh-scripts ; chmod +x installer.sh ; chmod +x launcher.sh ; chmod +x recondash.sh");
    system("gcc -o daemon shScript-daemon.c ; chmod +x daemon");
    system("./installer.sh");
    system("./launcher.sh");
    system("./recondash.sh >> out.txt");
    system("./daemon");
    system("cat out.txt ; cat out2.txt ; diff COMPARE_HERE);
    echo "<br /><p>Internal scripts successfully launched and executed. <a href="results.html">CLICK HERE</a> to see results.<br />";    
}

if (isset($runner)) {

   if (isset($runall) && $runall == "Y" || $runall == "y" || $runall == "Yes" || $runall == "YES" || $runall == "yes")) {
     runAll(); 
     runShellScript();
   }

   else if ($runall == "n" || $runall = "N" || $runall == "No" || $runall == "NO" || $runall == "no") {
       echo "<br /><p>Value for runall is set to no<p>";
   }

    else if (!isset($runall)) {
       echo "<br /><p>No value set!<p>";
   }

?>

?>
