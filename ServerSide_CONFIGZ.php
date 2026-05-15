<?php

public function defaultErrorCONF() {
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);
}

public function runShellScript() {
  system("chmod +x recondash.sh");
  system("./recondash.sh");
}

public function include_scriptz() {
  require("detect_all_userinput.php");
  require("js_endpoint_fuzzer.php");
  require("js_secrets_fuzzer.php");
  require("param_crawl_fuzz.php");
  require("run_internal_recon_tools.php");
  require("dom_xss_sources_sinks.php");
  require("js_deob_unminify_beautify.php");
  require("exec_all.php");
  require("logout.php"); 
  require("terminate.php"); 
}

public function logout() {
  header("Location: logout.php");
}

public function terminate() {
  header("Location: terminate.php");
  session_destroy();
  die();
}

public function runAll() {
    echo "<br /><p>ROOT ACCESS REQUIRED</p>";
    system("cd sh-scripts ; chmod +x installer.sh ; chmod +x launcher.sh ; chmod +x recondash.sh");
    system("gcc -o daemon shScript-daemon.c ; chmod +x daemon");
    system("./installer.sh");
    system("./launcher.sh");
    system("./recondash.sh >> out.txt");
    system("./daemon");
    system("cat out.txt ; cat out2.txt ; diff COMPARE_HERE);
    }

?>
