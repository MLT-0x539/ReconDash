<?php
    echo __dir__;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<?php session_start(); ?>

<?php
if (isset($_GET['logout'])) {
	if ($_GET['logout']==true) {
		//Simple exit message
		$fp = fopen("log.html", 'a');
		fwrite($fp, "<div class='msgln'><i>User ". $_SESSION['name'] ." has left the chat session.</i><br></div>");
		fclose($fp);
		session_destroy();
		header("Location: index.php");
	}
}
?>	
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>Team Collaboration Chat</title>
<link rel="stylesheet" href="style.css" />
</head>

<?php
if (!isset($_SESSION['name'])){
loginForm();
}
else 
{
?>
<div id="wrapper">
    <div id="menu">
        <p class="welcome">Welcome, <?php echo $_SESSION['name']; ?> <b></b></p>
        <p class="logout"><a id="exit" href='index.php?logout=true'>Exit Chat</a></p>
        <div style="clear:both"></div>
    </div>
     
    <div id="chatbox">
	<?php
	if(file_exists("log.html") && filesize("log.html") > 0){
    $handle = fopen("log.html", "r");
    $contents = fread($handle, filesize("log.html"));
    fclose($handle); 
    echo $contents;
	}
	?>
	</div>
     
    <form name="message" action="">
        <input name="usermsg" type="text" id="usermsg" size="63" />
        <input name="submitmsg" type="submit"  id="submitmsg" value="Send" />
    </form>
</div>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.3/jquery.min.js"></script>
<script src="chatbleh.js"></script>
<?php
}
?>
</body>
</html>

<?php
 
function loginForm(){
    echo'
    <div id="loginform">
    <form action="index.php" method="post">
        <p>Please enter your name to continue:</p>
        <label for="name">Name:</label>
        <input type="text" name="name" id="name" />
        <input type="submit" name="enter" id="enter" value="Enter" />
    </form>
    </div>
    ';
}
 
if(isset($_POST['enter'])){
    if($_POST['name'] != ""){
        $_SESSION['name'] = stripslashes(htmlspecialchars($_POST['name']));
		$fp = fopen("log.html", 'a');
		fwrite($fp, "<div class='msgln'><i>User ". $_SESSION['name'] ." has joined the chat.</i><br></div>");
		fclose($fp);
		header("Location: index.php");
    }
    else{
        echo '<span class="error">Please type in a name</span>';
    }
}

?>
