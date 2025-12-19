<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="/assets/installer.css">
  <title>Bash Script Installer</title>
 <style>
 	a {
		color: red;
		}
		a:hover {
		color: green;
		}
		
		.textinfo {
		font-size: 12px;
		}
    </style>
</head>
<body>
    <div class="container">
	<hr>
        <header>
            <h1>ReconDash</h1>
            <p class="subtitle"><b>Bug0xF4</b> - <i>Reconnaissance Command Center</i></p>
        </header>
		<hr>
		<br />
  <center>
  		<div class="dashboard-links">
		<button onclick="window.location='recondash.html'"><b>RETURN TO MAIN PAGE</b></button></a><br />
		</div>
		<br /><br />
   <h2>Required Bash Script Installer:</h2>
   </center>
   <div class="textinfo">
   <p>This script will download and install the required bash scripts onto your VPS. Many web-based aspects of this recon tool can still be used without the bash scripts, but for the tool
   to reach its full potential, you should also install these bash scripts for more extensive recon capabilities. In order to install these, select 'Y' as the option on the drop-down menu
   asking you if you want to install these, and select the Linux distribution that is running on your VPS. If you cannot see your Linux Distribution listed as an option within the drop-down
   menu, then just choose an option that uses the same package manager as your distro. If you aren't sure which option that would be, then take a look at the following list:</p>
   <a href="https://en.wikipedia.org/wiki/List_of_Linux_distributions">List of distros and their corresponding package managers</a>
   </div>
   <br /><br />
<form action="sh_installer.php" method="POST">
<b>Would you like to run the installer for the local tools on your machine?</b><br />
  <input type="checkbox" id="yes" name="yes" value="yes">
  <label for="yes"> Yes</label><br>
  <input type="checkbox" id="no" name="no" value="no">
  <label for="no"> No</label><br>
   <br />
      <b>Select your distro from the following menu:</b><br />
      <select>
        <option value="debian">Debian</option>
        <option value="ubuntu">Ubuntu</option>
        <option value="arch">Arch</option>
        <option value="kali">Kali</option>
        <option value="redhat">RHEL</option>
        <option value="Fedora">Fedora</option>
        <option value="Gentoo">Gentoo</option>
        <option value="Slackware">Slackware</option>
        <option value="Mint">Mint</option>
		<br />
      <input type="submit" value="submit">
     </select>
	 </form>
	 </div>
 </body> 
</html>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$installer = $_POST['install'];
$distro = $_POST['distro'];

if ($isset($distro)) {
 switch ($distro) {
     case debian:
         echo "<br /><p>Debian selected as distro. Passing this value as argument to installation script</p>";
         $distroval = "debian";
         break;
     case ubuntu:
         echo "<br /><p>Ubuntu selected as distro. Passing this value as argument to installation script</p>";
         $distroval = "ubuntu";
         break;
     case arch:
         echo "<br /><p>Arch selected as distro. Passing this value as argument to installation script</p>";
         $distroval = "arch";
         break;
     case kali:
         echo "<br /><p>Kali selected as distro. Passing this value as argument to installation script</p>";
         $distroval = "kali";
         break;
     case redhat:
         echo "<br /><p>Redhat selected as distro. Passing this value as argument to installation script</p>";
         $distroval = "RHEL";
         break;
     case Fedora:
         echo "<br /><p>Fedora selected as distro. Passing this value as argument to installation script</p>";
         $distroval = "fedora";
         break;
     case Slackware:
         echo "<br /><p>Slackware selected as distro. Passing this value as argument to installation script</p>";
         $distroval = "Slackware";
         break;
     case Gentoo:
         echo "<br /><p>Gentoo selected as distro. Passing this value as argument to installation script</p>";
         $distroval = "Gentoo";
         break;
     case Mint:
         echo "<br /><p>Linux Mint selected as distro. Passing this value as argument to installation script</p>";
         $distroval = "Mint";
         break;
   } 
}
 
else if (!$isset($distro)) {
   echo "<br /><p><b>ERROR: </b>No value set for distro!</p><br />";  
 } 

else {
   echo "<br /><p><b>UNKNOWN ERROR!</b></p>";
 }

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

else {
   echo "<br /><p><b>UNKNOWN ERROR!</b></p>";
}
?>
