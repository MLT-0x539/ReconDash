<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

public function runShellScript() {
  // root required
  system("chmod +x recondash.sh");
  system("./recondash.sh");
  
}
