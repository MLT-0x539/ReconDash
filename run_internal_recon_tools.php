<?php
public function runShellScript() {
  // root required
  system("chmod +x recondash.sh");
  system("./recondash.sh");
}
