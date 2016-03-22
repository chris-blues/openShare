<!DOCTYPE html>
<?php
$debug = false;
//$debug = true;

//error_reporting(E_ALL);
error_reporting(E_ALL & ~E_NOTICE);
ini_set("log_errors", 1);
ini_set("error_log", getcwd() . "/.php-error.log");
if ($debug)
  {
   ini_set("display_errors", 1);
  }
else
  {
   ini_set("display_errors", 0);
  }

$browserlang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
switch($browserlang)
  {
   case 'de': { $lang = "de_DE"; break; }
   default: { $lang = "en"; break; }
  }

$directory = '.locale';
$domain = 'share';
$locale = "$lang.utf8";// echo "<!-- locale set to => $locale -->\n";
setlocale( LC_MESSAGES, $locale);
bindtextdomain($domain, $directory);
textdomain($domain);
bind_textdomain_codeset($domain, 'UTF-8'); 

date_default_timezone_set("Europe/Berlin");
require_once('config.php');
require_once('functions.php');


// ==============  INIT  ===============
$rootPath = realpath("./");
$pathHtaccess = getcwd() . "/.htaccess";
$pathHtpasswd = getcwd() . "/.htpasswd";
$pathConfigPhp = getcwd() . "/config.php";
$pathQueue = getcwd() . "/.queue";
$pathUserfile = getcwd() . "/users";
//$error = array();
$phpErrorMsg = "";

$noPasswd = false;
$noHtaccess = false;
$reload = false;

if (file_exists($pathHtaccess)) { $ht["htaccess"] = true; }
if (file_exists($pathHtpasswd)) { $ht["htpasswd"] = true; }

if ($ht["htaccess"])
  { // setup complete, just check .htaccess for the right path to .htpasswd, and that .htpasswd contains at least one key
   $htaccess = file($pathHtaccess, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
   if (!isset($htaccess[0]) or $htaccess[0] == "") { unset($htaccess); $htaccess = populateHtaccess($pathHtpasswd); }
   $needle = "AuthUserFile";
   foreach ($htaccess as $line => $data)
     {
      if (strncmp($data, $needle, strlen($needle)) == 0)
        {
         $tmp = explode(" ", $data);
         if (strcmp($tmp[1], $pathHtpasswd) != 0)
           { // path is wrong! Change it!
            $noHtaccess = true;
            $htaccess[$line] = "# disabled by webspaceManager:\n#$data\n$needle $pathHtpasswd";
	   }
        }
     }
  }
else { $noHtaccess = true; $htaccess = populateHtaccess($pathHtpasswd); }

if ($ht["htpasswd"])
  {
   $htpasswd = file($pathHtpasswd, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
   if (count($htpasswd) < 1 or strlen($htpasswd[0]) < 2) { $noPasswd = true; }
  }
else { $noPasswd = true; }

foreach ($htpasswd as $key => $value)
  {
   if (strncmp("#", $value, 1) == 0) continue;
   $tmp = explode(":", $value);
   $user[] = $tmp[0]; // Build an array with only usernames! e.g.: $user[0]=>"user1"; $user[1]=>"user2"; ...
  }
unset($tmp);

if (isset($_POST["job"]) and $_POST["job"] == "addUser")
  {
   if (in_array($_POST["name"], $user))
     {
      $phpErrorMsg .= "Error: User already exists --> NOT adding user " . $_POST["name"] . ".\n";
      $error["userExists"] = true;
      $error["debug"] = debug_backtrace();
     }
   if (strcmp($_POST["passwd"], $_POST["passwdConfirm"]) != 0)
     {
      $phpErrorMsg .= "Error: Passwords don't match!\n";
      $error["passwordsNoMatch"] = true;
      $error["debug"] = debug_backtrace();
     }
   if (!$error["userExists"] and !$error["passwordsNoMatch"] and checkQueue($_POST["email"], $_POST["hash"], $pathQueue))
     { // if all tests have passed positively, then and only then write new data!
      $htpasswd = file($pathHtpasswd, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      $newpasswd = crypt($_POST["passwd"], base64_encode($_POST["passwd"]));
      $htpasswd[] = $_POST["name"] . ":" . $newpasswd;
      sort($htpasswd, SORT_NATURAL | SORT_FLAG_CASE);
      $handle = fopen($pathHtpasswd, "w");
      foreach ($htpasswd as $line => $data)
        {
         fwrite($handle, trim($data) . "\n");
        }
      fclose($handle);

      $handle = fopen($pathUserfile, "a");
      fwrite($handle, $_POST["name"] . " " . $_POST["email"] . " " . $_POST["hash"] . "\n");
      fclose($handle);

      writeLog("join.php: main(98: addUser)");
      cleanUpQueue($_POST["email"], $pathQueue);
      reloadIndex();
      exit;
     }
  }

if ($_POST["job"] == "newPasswd")
  { 	// $_POST: name, passwd, passwdConfirm
   if (strcmp($_POST["passwd"], $_POST["passwdConfirm"]) != 0) { $error["passwordsNoMatch"] = true; }
   if ($_POST["name"] == "") { echo gettext("Your name was not found!<br>\nUnauthorized access!<br>\n<br>\nExiting...<br>\n"); exit; }
   $htpasswd = file($pathHtpasswd, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
   foreach ($htpasswd as $key => $value)
     {
      if (strncmp($value, $_POST["name"], strlen($_POST["name"])) == 0) { unset ($htpasswd[$key]); }
     }

   if (!$error["passwordsNoMatch"])
     {
      $newpasswd = crypt($_POST["passwd"], base64_encode($_POST["passwd"]));
      $htpasswd[] = $_POST["name"] . ":" . $newpasswd;
      sort($htpasswd, SORT_NATURAL | SORT_FLAG_CASE);

      $handle = fopen($pathHtpasswd, "w");
      foreach ($htpasswd as $line => $data) fwrite($handle, trim($data) . "\n");
      fclose($handle);
      reloadIndex();
      exit;
     }
   if (count($error) > 0)
     { ?>
<html>
<head>
<meta charset="UTF-8">
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<title>share <?php echo $_SERVER["SERVER_NAME"]; ?></title>
<link rel="stylesheet" href=".style.css" type="text/css">
</head>
<body>
  <div id="main" style="text-align: center;">
    <?php echo gettext("<h1>There was some error!</h1>\n
    <p>Most propably your link in the mail was corrupted. Some email-clients do break longer lines, and in the process break links. Try to copy and paste the whole link into your browser by hand!</p>\n
    <p>Another possibility is, that something changed on the server. If you already tried the suggestion above, then please contact the admin, to check if your hash is intact and still in the users file!</p>\n"); ?>
  </div>
</body>
</html>
<?php }
   writeLog("join.php: main(115: newPasswd)");
   exit;
  }

if (isset($_GET["verification"]))
  {
   $queue = file($pathQueue, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
   $email = $_GET["email"];
   $hash = $_GET["verification"];

   foreach ($queue as $key => $value)
     {
      if (strncmp($value, "#", 1) == 0) continue; 		// Leave comments alone!
      if (strncmp($value, $email, strlen($email)) == 0)	// We found an email-match!
        {
         $emailInQueue = true;
         $tmp = explode(" ", $value);
         if (strcmp($tmp[1], $hash) == 0)
           {
            $hashVerified = true;
            $emailMatch = $key;
	   }
         unset ($tmp);
        }
     }

   if ($emailInQueue and $hashVerified and isset($emailMatch))
     {
?>
<html>
<head>
<meta charset="UTF-8">
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<title>share <?php echo $_SERVER["SERVER_NAME"]; ?></title>
<link rel="stylesheet" href=".style.css" type="text/css">
<script type="text/javascript">

var usernames = [
<?php $c = 0; foreach ($user as $key => $value) { if ($c == 0) { echo "\"$value\""; } else { echo ",\n\"$value\""; } $c++; } ?>
];

function checkInput ()
  {
   var error = false;
   name = document.getElementById("name").value;
   for (var i = 0; i < usernames.length; i++)
     {
      if (usernames[i] == name)
        {
         error = true;
         document.getElementById("error").style.display = "block";
         document.getElementById("error").innerHTML = "<?php echo gettext("This name is already used. Please choose another!"); ?>";
	}
     }
   passwd = document.getElementById('passwd');
   confirmPasswd = document.getElementById('confirmPasswd');
   if (passwd.value == confirmPasswd.value && passwd.value != "" && document.getElementById('name').value != "")
     {  }
   else 
     {
      error = true;
      passwd.style.backgroundColor = "red";
      passwd.value = "";
      confirmPasswd.style.backgroundColor = "red";
      confirmPasswd.value = "";
      document.getElementById("error").style.display = "block";
      document.getElementById("error").innerHTML = "<?php echo gettext("The passwords don't match! Please try again!"); ?>";
     }
   if (error != true) document.getElementById("setPasswd").submit();
  }
</script>
</head>
<body>
  <div id="main" style="text-align: center;">
    <h1><?php echo gettext("Enter your data"); ?></h1>
    <form id="setPasswd" action="join.php" method="post" accept-charset="UTF-8" enctype="multipart/form-data">
      <input type="hidden" name="job" value="addUser">
      <input type="hidden" name="email" value="<?php echo $email; ?>">
      <input type="hidden" name="hash" value="<?php echo $hash; ?>">
      <div>
        <table border="0" style="margin: 0px auto;">
          <tr><td><?php echo gettext("name:"); ?></td><td><input id="name" type="text" name="name" value=""></td></tr>
          <tr><td><?php echo gettext("password:"); ?></td><td><input id="passwd" type="password" name="passwd" onfocus="document.getElementById('passwd').backgroundColor = 'white';"></td></tr>
          <tr><td><?php echo gettext("confirm password:"); ?></td><td><input id="confirmPasswd" type="password" name="passwdConfirm" onfocus="document.getElementById('confirmPasswd').backgroundColor = 'white';"></td></tr>
        </table>
      </div>
      <button type="submit" onclick="checkInput();">OK</button>
      <div id="error" style="display: none; background-color: red;">
        <!-- Error messages will go here -->
      </div>
    </form>
  </div>
</body>
</html>
<?php }
   writeLog("join.php: main(159: verification)");
  }

if (isset($_GET["job"]) and $_GET["job"] == "resetPasswd")
  {
   $users = file($pathUserfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
   foreach ($users as $key => $value)
     {
      $tmp = explode(" ", $value);
      if (strcmp($_GET["email"], $tmp[1]) == 0)
        {
         if (isset($emailFound) and $emailFound) { $error["multipleEmails"] = true; $multipleEmails[] = $key; }
         $emailFound = true;
         if (strcmp($_GET["verification"], $tmp[2]) == 0)
           {
            $concerningUser["name"] = $tmp[0];
            $concerningUser["email"] = $tmp[1];
            $concerningUser["hash"] = $tmp[2];
            $matchFound = true;
	   }
	 else { $error["hashNoMatch"] = true; }
        }
      if ($debug) // and isset($error))
        { echo "Errors:<pre>"; foreach ($error as $key => $value) { echo "$key\n"; } echo "</pre><br>\n"; }
     }
   if (isset($error["multipleEmails"]))
     {
      foreach ($multipleEmails as $key => $value)
        {
         unset($users[$value]);
        }
      $users[] = $concerningUser["name"] . " " . $concerningUser["email"] . " " . $concerningUser["hash"];
      sort($users, SORT_NATURAL | SORT_FLAG_CASE);
      $handle = fopen($pathUserfile, "w");
      foreach ($users as $key => $value) fwrite ($handle, trim($value) . "\n");
      fclose($handle);
     }
?>
<html>
<head>
<meta charset="UTF-8">
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<title>share <?php echo $_SERVER["SERVER_NAME"]; ?></title>
<link rel="stylesheet" href=".style.css" type="text/css">
<script type="text/javascript">

function checkInput ()
  {
   var error = false;
   passwd = document.getElementById('passwd');
   confirmPasswd = document.getElementById('confirmPasswd');
   if (passwd.value == confirmPasswd.value && passwd.value != "" && document.getElementById('name').value != "")
     {  }
   else 
     {
      error = true;
      passwd.style.backgroundColor = "red";
      passwd.value = "";
      confirmPasswd.style.backgroundColor = "red";
      confirmPasswd.value = "";
      document.getElementById("error").style.display = "block";
      document.getElementById("error").innerHTML = "<?php echo gettext("The passwords don't match! Please try again!"); ?>";
     }
   if (error != true) document.getElementById("setPasswd").submit();
  }
</script>
</head>
<body>
  <div id="main" style="text-align: center;">
    <h1><?php echo gettext("Enter your data"); ?></h1>
    <form id="setPasswd" action="join.php" method="post" accept-charset="UTF-8" enctype="multipart/form-data">
      <input type="hidden" name="job" value="newPasswd">
      <input type="hidden" name="name" value="<?php echo $concerningUser["name"]; ?>">
      <div>
        <table border="0" style="margin: 0px auto;">
          <tr><td><?php echo gettext("new password:"); ?></td><td><input id="passwd" type="password" name="passwd" onfocus="document.getElementById('passwd').backgroundColor = 'white';"></td></tr>
          <tr><td><?php echo gettext("confirm password:"); ?></td><td><input id="confirmPasswd" type="password" name="passwdConfirm" onfocus="document.getElementById('confirmPasswd').backgroundColor = 'white';"></td></tr>
        </table>
      </div>
      <button type="submit" onclick="checkInput();">OK</button>
      <div id="error" style="display: none; background-color: red;">
        <!-- Error messages will go here -->
      </div>
    </form>
  </div>
</body>
</html>
<?php
   writeLog("join.php: main(252: resetPasswd)");
  }

if (!isset($_GET["verification"]) and !isset($_GET["job"]))

  { ?>
<html>
<head>
<meta charset="UTF-8">
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<title>share <?php echo $_SERVER["SERVER_NAME"]; ?></title>
<link rel="stylesheet" href=".style.css" type="text/css">
</head>
<body>
  <div id="main" style="text-align: center;">
    <?php echo gettext("<h1>Sorry, you're not allowed to enter!</h1>\n
    <p>Maybe something went wrong (e.g. you waited too long to come here, or someone already removed you again from our waiting list).</p>\n
    <p>Ask your admin or invitor to add you again, if you think you should be allowed in here.</p>\n"); ?>
  </div>
</body>
</html>
<?php }
  writeLog("join.php: main (339: verification failed)");
?>