<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$installer = $_POST['install'];
$distro = $_POST['distro'];
$distroval = $distro; // will modify this for distro name formatting before concatenation into syscall

if (isset($Installer) && $installer == "Y") {
  system("chmod +x installer.sh");
  system("./installer.sh ".$distroval.);
}

else if (isset($Installer) && $installer == "N") {
  echo "<br /><p>You chose 'No' as the option for the installation process.</p><br />";
  echo "<p>If this is a mistake, then refresh the page and select the 'Y' option</p><br />";
  echo "<p><b>NOTE: </b>Manual installation can be done via connecting to your VPS and running the 'installer.sh' script</p><br />";
}

else if (!isset($Installer)) {
  echo "<br /><p><b>ERROR: </b>No value set for installation script!</p><br />";  
} 

switch ($distro) {
    case debian:
        echo "<br /><p>Debian selected as distro. Passing this value as argument to installation script</p>";
        break;
    case ubuntu:
        echo "<br /><p>Ubuntu selected as distro. Passing this value as argument to installation script</p>";
        break;
    case arch:
        echo "<br /><p>Arch selected as distro. Passing this value as argument to installation script</p>";
        break;
    case kali:
        echo "<br /><p>Kali selected as distro. Passing this value as argument to installation script</p>";
        break;
    case RHEL:
        echo "<br /><p>Redhat selected as distro. Passing this value as argument to installation script</p>";
        break;
    case Fedora:
        echo "<br /><p>Fedora selected as distro. Passing this value as argument to installation script</p>";
        break;
    case Slackware:
        echo "<br /><p>Slackware selected as distro. Passing this value as argument to installation script</p>";
        break;
    case Gentoo:
        echo "<br /><p>Gentoo selected as distro. Passing this value as argument to installation script</p>";
        break;
    case Mint:
        echo "<br /><p>Linux Mint selected as distro. Passing this value as argument to installation script</p>";
        break;
}
