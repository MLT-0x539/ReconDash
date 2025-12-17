<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$installer = $_POST['install'];
$distro = $_POST['distro'];

if (isset($Installer) && $installer == "Y") {
  system("chmod +x installer.sh");
  system("./installer.sh");
}

else if (isset($Installer) && $installer == "N") {
  echo "<br /><p>You chose 'No' as the option for the installation process.</p><br />";
  echo "<p>If this is a mistake, then refresh the page and select the 'Y' option</p><br />";
  echo "<p><b>NOTE: </b>Manual installation can be done via connecting to your VPS and running the 'installer.sh' script</p><br />";
}

else if (!isset($Installer)) {
  echo "<br /><p><b>ERROR: </b>No value set for installation script!</p><br />";  
} 
