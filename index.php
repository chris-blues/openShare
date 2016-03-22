<!DOCTYPE html>
<?php
$start = microtime(true);
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


ini_set('max_execution_time', '300');
ini_set('sendmail_from', 'admin@' . $_SERVER["SERVER_NAME"]);

$browserlang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
switch($browserlang)
  {
   case 'de': { $lang = "de_DE"; break; }
   default: { $lang = "en"; break; }
  }

$directory = '.locale';
$domain = 'share';
$locale = "$lang.utf8"; echo "<!-- locale set to => $locale -->\n";
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
$error = array();
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
   foreach ($htaccess as $line => $data)
     {
      $needle = "AuthUserFile";
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

$username = $_SERVER['REMOTE_USER'];
if (in_array($username, $admins))
  {
   $admin = true;
   $usernames = $htpasswd;
   foreach ($usernames as $key => $value)
     {
      if (strncmp("#", $value, 1) == 0) continue;
      $tmp = explode(":", $value);
      $user[] = $tmp[0];
     }
   unset($tmp, $usernames);
  }

if ($noPasswd and !isset($_POST["passwd"])) // diplay simple html page to enter user data and send it via $_POST
  {
   ?>
<html>
<head>
<meta charset="UTF-8">
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<link rel="stylesheet" href=".style.css" type="text/css">
<script type="text/javascript">

var usernames = [
<?php if (isset($user) and count($user) > 0) { foreach ($user as $key => $value) { if($key == 0) echo "\"$value\""; else echo ",\n\"$value\""; } } ?>
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
         document.getElementById("error").innHTML = "<?php echo gettext("This name is already used. Please choose another!"); ?>";
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
      document.getElementById("error").innHTML = "<?php echo gettext("The passwords don't match! Please try again!"); ?>";
     }
   if (error != true) document.getElementById("setPasswd").submit();
  }
</script>
</head>
<body>
  <div id="main" style="text-align: center;">
    <h1>Enter first User's data</h1>
    <form id="setPasswd" action="index.php" method="post" accept-charset="UTF-8" enctype="multipart/form-data">
      <input type="hidden" name="job" value="<?php if (isset($_GET["job"]) and $_GET["job"] == "joinUser") echo "addUser"; else echo "init"; ?>">
      <div>
        <table border="0" style="margin: 0px auto;">
          <tr><td><?php echo gettext("name:"); ?></td><td><input id="name" type="text" name="name" value=""></td></tr>
          <tr><td><?php echo gettext("password:"); ?></td><td><input id="passwd" type="password" name="passwd" onfocus="document.getElementById('passwd').backgroundColor = 'white';"></td></tr>
          <tr><td><?php echo gettext("confirm password:"); ?></td><td><input id="confirmPasswd" type="password" name="passwdConfirm" onfocus="document.getElementById('confirmPasswd').backgroundColor = 'white';"></td></tr>
        </table>
      </div>
      <button type="button" onclick="checkInput();">OK</button>
      <div id="error" style="display: none; background-color: red;">
        <!-- Error messages will go here -->
      </div>
    </form>
  </div>
</body>
</html>
   <?php 
   if ($_POST["job"] != "init" or !isset($_POST["job"])) exit;
  }

if ($_POST["job"] == "init")
  {
   unset($htpasswd);
   if (strcmp($_POST["passwd"], $_POST["passwdConfirm"]) != 0) 
     {
      $phpErrorMsg .= "passwords don't match!\n";
      //foreach ($_POST as $key => $value) echo "\$_POST[$key] =&gt; $value<br>\n";
      exit;
     }
   $htpasswd[] = "# generated by webspaceManager";
   $htpasswd[] = $_POST["name"] . ":" . crypt($_POST["passwd"], base64_encode($_POST["passwd"]));
   $noPasswd = true; 	// reset .htpasswd
   $noHtaccess = true; 	// reset .htaccess
   $reload = true;
  }

if ($noPasswd and !$error["transmission"] and !$error["passwdCheck"]) writeHtpasswd($pathHtpasswd, $htpasswd);
if ($noHtaccess) writeHtaccess($pathHtaccess, $htaccess);
// add first user to admins
if ($_POST["job"] == "init") changeAdmin($pathConfigPhp, $_POST["name"], "add");
if ($_POST["job"] == "changeAdmin")
  {
   changeAdmin($pathConfigPhp, $_POST["name"], $_POST["operation"]);
   $reload = true;
  }

// ==============  INIT  ===============

// =============  CONFIG  ==============

if (isset($_GET["dir"])) $actualPath = realpath("$rootPath/{$_GET["dir"]}");
else $actualPath = realpath("$rootPath/$forward");
$path = str_replace("$rootPath/", "", $actualPath);

// Make sure we never go below "$rootPath/$forward" !!!
// example:
// if $rootpath == /your/path/to/www/share/data
// and $actualPath == /your/path/to/www/share/data/../..
// then fallback to $forward (defined in .config.php)
if (strlen("$rootPath/$forward") >= strlen($actualPath)) $path = $forward;

$cwd = getcwd();
$depth = 0;

// =============  CONFIG  ==============

// ===============  JOBS  ==================

if ($_POST["job"] == "logout")
  {
   $logoutPath = str_replace("index.php", "", $_SERVER["SCRIPT_NAME"]);
   $logoutTarget = $_SERVER["HTTP_X_FORWARDED_PROTO"] . "://@" . $_SERVER["SERVER_NAME"] . $logoutPath . ".logout.php";
  }

if ($_POST["job"] == "del")
  {
   foreach ($_POST["filename"] as $key => $value)
     {
      if (count($_POST["filename"]) < 1) break;
      if ($path == "") $delPath = "$cwd/$value";
      else $delPath = "$cwd/$path/$value";
      if (is_dir($delPath))
        {
         if (!rmdir($delPath))
           {
            cleanDir($delPath);
            if (!rmdir($delPath)) { $phpErrorMsg .= "Error! Dir $path/$value could not be deleted.\n"; $error["rmDir"] = true; $error["debug"] = debug_backtrace(); }
           }
        }
      else { if (!unlink($delPath)) { $phpErrorMsg .= "Error! File $path/$value could not be deleted.\n"; $error["rmFile"] = true; $error["debug"] = debug_backtrace(); }}
     }
   //$reload = true;
  }

if ($_POST["job"] == "rename")
  {
  // $path = realpath("./");
   if(rename("$cwd/$path/" . $_POST["oldFilename"], "$cwd/$path/" . $_POST["newFilename"])) 
     {
      $perms = getFilePerms("$cwd/$path/" . $_POST["newFilename"]);
      if ($perms != "0755") { chmod ("$cwd/$path/" . $_POST["newFilename"], 0755); }
     }
   else 
     {
      $phpErrorMsg .= "Error renaming $cwd/$path/{$_POST["oldFilename"]}\n";
      $error["rename"] = true;
      $error["debug"] = debug_backtrace();
      writeLog("index.php: main(243: job=rename)");
     }
   //$reload = true;
  }

if ($_POST["job"] == "createDir")
  {
   if (!mkdir("$cwd/{$_POST["path"]}/{$_POST["newDir"]}", 0755, true))
     {
      $phpErrorMsg .= "Error creating dir: $cwd/{$_POST["path"]}/{$_POST["newDir"]}\n";
      $error["mkdir"] = true;
      $error["debug"] = debug_backtrace();
      writeLog("index.php: main(261: job=createDir)");
     }
   else
     {
      $perms = getFilePerms("$cwd/{$_POST["path"]}/{$_POST["newDir"]}");
      if ($perms != "0755") { chmod ("$cwd/{$_POST["path"]}/{$_POST["newDir"]}", 0755); }
     }
   //$reload = true;
  }

if ($_POST["job"] == "move")
  {
   if ($path == "") { $targetDir = $_POST["moveTo"]; $origin = ""; }
   else { $targetDir = $_POST["moveTo"]; $origin = "$path/"; }
   foreach ($_POST["filename"] as $key => $value)
     {
      if (!rename("$origin$value", "$targetDir/$value"))
        { $phpErrorMsg .= "Error moving $origin$value to $targetDir/$value\n"; $error["move"] = true; $error["debug"] = debug_backtrace(); return false; }
      else
        {
	 $perms = getFilePerms("$targetDir/$value");
	 if ($perms != "0755") { chmod ("$targetDir/$value", 0755); }
	}
     }
  }

if ($_POST["job"] == "downloadZip")
  {
   if ($path == "") { $origin = ""; $topDir = $_SERVER["SERVER_NAME"]; }
   else
     {
      $origin = "$path/";
      if ($path == $forward) { $topDir = $_SERVER["SERVER_NAME"]; }
      else
        {
         $tmp = explode("/", $path);
         foreach ($tmp as $value) { $topDir = $value; }
        }
      }
   $zipName = "$forward/{$topDir}_{$username}_" . date("Y-m-d-H-i-s");

   // check if our filename is unique
   if (file_exists("$zipName.zip"))
     {
      $zipNameUnique = false;
      $c = 0;
      while (!$zipNameUnique)
        {
         if (file_exists("$zipName_$c.zip")) { $zipNameUnique = false; }
         else { $zipName .= "_$c"; $zipNameUnique = true; }
         $c++;
        }
     }
   $zipName .= ".zip";

   $debugs["origin"] = $origin;
   $debugs["zipname"] = $zipName;
   $debugs["filename"] = $_POST["filename"];

   if (!create_zip($_POST["filename"], $zipName))
     {
      $error["create_zipFailed"] = true;
      $error["debug"] = debug_backtrace();
      $phpErrorMsg .= "create_zip(" . $_POST["filename"] . ", $zipName) failed!\n";
     }
   $downloadZipFile = "$zipName";
   writeLog("index.php: main(294: downloadZip)");
  }

if ($_POST["job"] == "changePasswd")
  {
   if (strcmp($_POST["newPasswd"], $_POST["newPasswdConfirm"]) != 0)
     {
      $phpErrorMsg .= "Error: Passwords don't match!\n";
      $error["debug"] = debug_backtrace();
      $error["passwordsNoMatch"] = true;
     }
   else
     {
      $newpasswd = crypt($_POST["newPasswd"], base64_encode($_POST["newPasswd"]));
      $length = strlen($username);

      $htpasswd = file(".htpasswd", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      sort($htpasswd, SORT_NATURAL | SORT_FLAG_CASE);
      foreach ($htpasswd as $line => $data)
        {
         $htpasswd[$line] = trim($data);
	 if (strncmp($username, $data, $length) == 0)
           {
            $htpasswd[$line] = "$username:$newpasswd";
           }
	}
      $handle = fopen(".htpasswd", "w");
      foreach ($htpasswd as $line => $data)
        {
         fwrite($handle, trim($data) . "\n");
        }
      fclose($handle);

      writeLog("index.php: main(337 changePasswd)");
     }
  }

if ($_POST["job"] == "removeUser" and $admin)
  {
   if (in_array($_POST["name"], $admins)) { changeAdmin($pathConfigPhp, $_POST["name"], "remove"); }

   $htpasswd = file(".htpasswd", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
   sort($htpasswd, SORT_NATURAL | SORT_FLAG_CASE);
   $length = strlen($_POST["name"]);
   $handle = fopen(".htpasswd", "w");
   foreach ($htpasswd as $line => $data)
     {
      if (strncmp($_POST["name"], $data, $length) == 0) continue;
      fwrite($handle, trim($data) . "\n");
     }
   fclose($handle);

   $userlist = file($pathUserfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
   $handle = fopen($pathUserfile, "w");
   foreach ($userlist as $key => $value)
     {
      if (strlen($value) < 2) continue;
      if (strncmp($value, $_POST["name"], strlen($_POST["name"])) != 0) fwrite ($handle, trim($value) . "\n");
     }
   fclose ($handle);

   writeLog("index.php: main(371 removeUser)");

   $reload = true;
  }

if ($_POST["job"] == "inviteUser")
  {
   $email = $_POST["email"];
   $from = "admin@" . $_SERVER["SERVER_NAME"];
   $maildate = date(DATE_RFC2822);
   $hash = hash("md5", "$maildate $email $start");
   $link = "https://" . $_SERVER["SERVER_NAME"] . str_replace("index.php", "join.php", $_SERVER["SCRIPT_NAME"]) . "?email=$email&verification=$hash";
   $resetPasswd = "https://" . $_SERVER["SERVER_NAME"] . str_replace("index.php", "join.php", $_SERVER["SCRIPT_NAME"]) . "?job=resetPasswd&email=$email&verification=$hash";
   $subject = sprintf(gettext("Invitation to join our webspace at %s"), $_SERVER["SERVER_NAME"]);
   $header = "To: $email\r\n";
   $header .= "From: $from\r\n";
   $header .= "Date: $maildate\r\n";
   $header .= "MIME-Version: 1.0\r\n";
   $header .= "Content-Type: text/plain; charset=\"UTF-8\";\r\n";
   $header .= "Content-Transfer-Encoding: 8bit\r\n";
   $header .= "X-Mailer: PHP/" . phpversion() . "\r\n";
   $header .= "\r\n";

   $message = gettext("Hi!");
   $message .= "\n\n";
   $message .= sprintf(gettext("You have been invited by %s to join our webspace at %s - all you have to do is click on the following link. Make sure it's not broken in two (some email-clients do such nasty things!)\n"), $_SERVER["REMOTE_USER"], $_SERVER["SERVER_NAME"]);
   $message .= sprintf(gettext("Then you will have to choose a username and password, and you're good to go.\n\n%s\n\n"), $link);
   $message .= sprintf(gettext("If you should forget your password click on this link to reset it:\n\n%s\n\n\n"), $resetPasswd);
   $message .= sprintf(gettext("Keep this mail in a safe place, for you might need it some day!\n\n\nBest regards,\n\n%s\n\n\nPlease don't respond to this mail!"), $_SERVER["REMOTE_USER"]);

   $result = mail(NULL, $subject, wordwrap($message, 70), $header, "-f$from");
   if (!$result)
     {
      $error["sendMail"] = true;
      $error["debug"] = debug_backtrace();
      $phpErrorMsg .= "main(694: job=inviteUser): Error: unable to send mail. result is ";
      if ($result === true) $phpErrorMsg .= "TRUE";
      else $phpErrorMsg .= "FALSE";
      $phpErrorMsg .= "\n";
      writeLog("index.php: main(399: job=inviteUser)");
     }
   if (file_exists(".queue")) $queue = file(".queue", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
   else $queue = array();
   foreach ($queue as $key => $value)
    {
     if (strlen($value) < 2) unset($queue[$key]);	// get rid of empty lines!
     if (strncmp($email, $value, strlen($email)) == 0) 	// if our new member already is in the queue:
        unset($queue[$key]); 				// remove the old entry from the array!
     $tmp = explode (" ", $value);
     if ($tmp[2] < time() - 15552000) 	// if this queue-member is more than half a year old:
       unset($queue[$key]);		// remove this entry
     unset($tmp);
    }
   $handle = fopen(".queue", "w");
   foreach ($queue as $key => $value) fwrite($handle, trim($value) . "\n");
   fwrite ($handle, "$email $hash " . time() . "\n");
   fclose($handle);
   writeLog("index.php: main(399: inviteUser)");
  }

/* if ($_POST["job"] == "addUser")
  {
   if (in_array($_POST["user"], $user))
     {
      $phpErrorMsg .= "Error: User already exists --> NOT adding user " . $_POST["user"] . ".\n";
      $error["userExists"] = true;
      $error["debug"] = debug_backtrace();
     }
   if (strcmp($_POST["newPasswd"], $_POST["passwdConfirm"]) != 0)
     {
      $phpErrorMsg .= "Error: Passwords don't match!\n";
      $error["passwordsNoMatch"] = true;
      $error["debug"] = debug_backtrace();
     }
   if (!$error["userExists"] and !$error["passwordsNoMatch"])
     { // if all tests have passed positively, then and only then write new data!
      $htpasswd = file(".htpasswd", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      $newpasswd = crypt($_POST["newPasswd"], base64_encode($_POST["newPasswd"]));
      $htpasswd[] = $_POST["user"] . ":" . $newpasswd;
      sort($htpasswd, SORT_NATURAL | SORT_FLAG_CASE);
      $handle = fopen(".htpasswd", "w");
      foreach ($htpasswd as $line => $data)
        {
         fwrite($handle, trim($data) . "\n");
        }
      fclose($handle);

      writeLog("index.php: main(454 addUser)");
      cleanUpQueue($_POST["email"], $_POST["hash"], $pathQueue);

      $reload = true;
     }
  } */

// ===============  JOBS  ==================

$FolderStructure = readDirStructure();

?>
<html>
<head>
<meta charset="UTF-8">
<?php if ($reload and !$debug) echo "<meta http-equiv=\"refresh\" content=\"0; URL=index.php?dir=$path\">\n"; ?>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<title>share <?php echo $_SERVER["SERVER_NAME"]; ?></title>
<link rel="stylesheet" href=".style.css" type="text/css">
<?php if ($_POST["job"] == "logout") { ?></head><body><h1 style="text-align: center; vertical-align: middle;">log out</h1></body></html><?php exit; } ?>
<script type="text/javascript">

function getUnit(index)
 {
  switch(index)
    {
     case 0: unit = " Bytes"; break;
     case 1: unit = " kiB"; break;
     case 2: unit = " MiB"; break;
     case 3: unit = " GiB"; break;
    }
  return unit;
 }

function fileSelected()
 {
  document.getElementById('fileName').innerHTML = "";
  var files = document.getElementById('fileToUpload').files;
  var filesize = 0;
  var filesizeTotal = 0;
  var iter = 0;
  for (var i = 0; i < files.length; i++)
    {
     iter = 0;
     filesize = 0;
     filesize = files[i].size;
     filesizeTotal += filesize;
     while (filesize > 1024)
       {
        filesize = Math.round(filesize * 100 / 1024) / 100;
        iter++;
       }
    filesize += getUnit(iter);

    document.getElementById('fileName').innerHTML += files[i].name + " (" + filesize + ")<br>";
   }
  iter = 0;
  while (filesizeTotal > 1024)
    {
     filesizeTotal = Math.round(filesizeTotal * 100 / 1024) / 100;
     iter++;
    }
  document.getElementById('fileSize').innerHTML = "<b>Total: " + filesizeTotal + getUnit(iter) + "</b>";
 }

function uploadFile()
 {
  var files = document.getElementById('fileToUpload').files;
  var fd = new FormData();
  for (var i = 0; i < files.length; i++)
    {
     fd.append("files[]", files[i]);
    }
  fd.append("dir", "<?php echo $directory; ?>");
  var xhr = new XMLHttpRequest();
  xhr.upload.addEventListener("progress", uploadProgress, false);
  xhr.addEventListener("load", uploadComplete, false);
  xhr.addEventListener("error", uploadFailed, false);
  xhr.addEventListener("abort", uploadCanceled, false);
  xhr.open("POST", ".uploadfile.php?dir=<?php echo $path; ?>");

  startTime = (new Date()).getTime();
  xhr.send(fd);
 }

var startTime;

function uploadProgress(evt)
 {
  var iter = 0;
  var sec = 0;
  var min = 0;
  var hour = 0;
  var prettyTime = "";
  var showHours = false;

  var now = (new Date()).getTime(); 
  var elapsedtime = now - startTime;
  elapsedtime = elapsedtime / 1000;
  var eta = ((evt.total / evt.loaded) * elapsedtime) - elapsedtime;
  sec = Math.round(eta);
  if (sec > 60)
    {
     min = Math.floor(sec / 60);
     sec = sec - (min * 60);
     if (min > 60)
       {
        hour = Math.floor(min / 60);
        min = min - (hour * 60);
        showHours = true;
       }
    }
  if (sec < 10) sec = "0" + sec;
  if (showHours == true) prettyTime = hour + ":" + min + ":" + sec;
  else prettyTime = min + ":" + sec;

  document.getElementById('progressNumber').style.display = 'block';
  document.getElementById('progress').style.display = 'block';
  totalBytes = evt.total;
  while (totalBytes > 1024)
    {
     totalBytes = Math.round(totalBytes * 100 / 1024) / 100;
     iter++;
    }
  totalBytes += getUnit(iter);
  iter = 0;

  if (evt.lengthComputable)
   {
    var percentComplete = Math.round(evt.loaded *100 * 100 / evt.total) / 100;
    var transferredBytes = evt.loaded;
    while (transferredBytes > 1024)
      {
       transferredBytes = Math.round(transferredBytes * 100 / 1024) / 100;
       iter++;
      }
    transferredBytes += getUnit(iter);

    document.getElementById('progressNumber').innerHTML = transferredBytes + "/" + totalBytes + " - <b>" + percentComplete.toString() + '%</b> - ' + prettyTime + " <?php echo gettext("remaining"); ?>";
    document.getElementById('progressbar').style.width = percentComplete.toString() + '%';
   }
  else
   {
    document.getElementById('progressNumber').innerHTML = '<?php echo gettext("unable to compute"); ?>';
   }
 }

function uploadComplete(evt)
 {
  /* This event is raised when the server send back a response */
  document.getElementById('progressNumber').innerHTML = evt.target.responseText;
  setTimeout("document.reload.submit()" , 1000);
  document.location.reload(true);
 }

function uploadFailed(evt)
 {
  alert("<?php echo gettext("There was an error attempting to upload the file."); ?>");
 }

function uploadCanceled(evt)
 {
  alert("<?php echo gettext("The upload has been canceled by the user or the browser dropped the connection."); ?>");
 }


function renameFile(oldFilename)
 {
  var filename = document.getElementById(oldFilename).getAttribute("data-filename");
  document.getElementById('fileCommandsWrapper').style.display = "block";
  document.getElementById("rename").style.display = "block";
  document.getElementById("oldFilename").value = filename;
  document.getElementById("newFilename").value = filename;
 }

function switchJob (job)
 {
  document.getElementById("job").value = job;
  document.getElementById("fileCommand").submit();
 }

function DeleteCheck () {
  var chk = window.confirm("<?php echo gettext("Do you really want to delete this???"); ?>");
  return (chk);
}

function toggleBox (boxId)
 {
  var box = document.getElementById(boxId);
  if (box.style.display == "none")
       {
        boxes = document.getElementsByClassName('userMenuBox');
        for (i = 0; i < boxes.length; i++)
          {
           boxes[i].style.display = "none";
          }
	if (boxId == "userSettings") document.getElementById("fileCommandsWrapper").style.display = "none";
	if (boxId == "fileCommandsWrapper") document.getElementById("userSettings").style.display = "none";
	box.style.display = "block";
       }
  else
       {
        box.style.display = "none";
       }
 }

function toggleCheckboxFile()
  {
   var checkboxes = document.getElementsByClassName("checkboxFile");
   if (document.getElementById("masterCheckboxFile").checked == true) var masterCheckboxFiles = true;
   else var masterCheckboxFiles = false;
   for (i = 0; i < checkboxes.length; i++)
     {
      if (masterCheckboxFiles == true) checkboxes[i].checked = true;
      else checkboxes[i].checked = false;
     }
  }

function displayErrorMessages()
  {
   alert("<?php echo str_replace("\n", "\\n", strip_tags($phpErrorMsg)); ?>");
  }

</script>
</head>
<?php 
if (strlen($phpErrorMsg) > 1) echo "<body onload=\"displayErrorMessages();\">\n";

if ($_POST["job"] == "downloadZip") { ?><body onload="document.getElementById('downloadZipFile').submit();">
  <form id="downloadZipFile" method="get" action="<?php echo $downloadZipFile; ?>"></form>
<?php }

if (!strlen($phpErrorMsg) > 1 and $_POST["job"] != "downloadZip") echo "<body>\n"; ?>
  <div id="main">
<?php
  $topDir = explode("/",getcwd());
  foreach ($topDir as $key => $value) $rootDir = $value;
  $folders = explode("/", $path);
?>
    <div class="shadow folders"><a href="index.php"><?php echo $_SERVER["HTTP_HOST"] . "/$forward"; ?></a></div>
<?php foreach ($folders as $key => $value) 
              {
               if ($value == "" or !isset($value) or $value == $forward) { $last = $value; continue; }
               echo "    <div class=\"shadow folders\"><a href=\"index.php?dir="; if ($last != "" and isset($last)) { echo "$last/"; } echo "$value\">$value</a></div>\n";
               if ($last == "" or !isset($last)) $last = $value;
               else $last .= "/$value";
	      }
      ?>
    <div id="usermenuBox">
      <h2 id="usermenu" onclick="toggleBox('userSettings');"><!--img src=".icons/usermenu.png" alt="icon user settings"--> <?php echo $_SERVER['REMOTE_USER']; ?></h2>
      <div id="userSettings" style="display: none;">
        <hr>
        <ul class="userSettings">
          <li>
            <div onclick="toggleBox('PasswdBox');"><a><?php echo gettext("change password"); ?></a></div>
            <div id="PasswdBox" class="userMenuBox">
              <form id="changePasswd" action="index.php?dir=<?php echo $path; ?>" method="post" accept-charset="UTF-8" enctype="multipart/form-data">
                <input type="hidden" name="job" value="changePasswd">
                <label for="newPasswd"><?php echo gettext("new password:"); ?></label>
                <input id="newPasswd" class="inputFieldSettings" type="password" name="newPasswd"><br>
                <label for="confirmPasswd"><?php echo gettext("confirm password:"); ?></label>
                <input id="confirmPasswd" class="inputFieldSettings" type="password" name="newPasswdConfirm"><br>
                <button type="submit" onclick="this.form.submit();"><?php echo gettext("change password"); ?></button>
              </form>
            </div>
          </li>
<?php if ($admin) { ?>
	</ul>
	<hr>
	<p style="text-align: center; font-size: 0.8em; margin: 0px;"><?php echo gettext("admin options"); ?></p>
	<ul class="userSettings" style="margin-top: 0px; padding: 0px 20px 5px;">
          <li>
            <div onclick="toggleBox('addUserBox');"><a><?php echo gettext("invite user"); ?></a></div>
            <div id="addUserBox" class="userMenuBox">
              <form id="inviteUser" action="index.php?dir=<?php echo $path; ?>" method="post" accept-charset="UTF-8" enctype="multipart/form-data">
                <input type="hidden" name="job" value="inviteUser">
                <label for="email"><?php echo gettext("email:"); ?></label><input id="email" class="inputFieldSettings" type="text" name="email" value=""><br>
                <button type="submit" onclick="this.form.submit();"><?php echo gettext("invite user"); ?></button>
              </form>
            </div>
          </li>
          <li>
            <div onclick="toggleBox('removeUserBox');"><a><?php echo gettext("remove user"); ?></a></div>
            <div id="removeUserBox" class="userMenuBox">
              <form id="removeUser" action="index.php?dir=<?php echo $path; ?>" method="post" accept-charset="UTF-8" enctype="multipart/form-data">
                <input type="hidden" name="job" value="removeUser">
                <select name="name">
<?php
foreach ($user as $key => $name)
  { 
   echo "                  <option value=\"$name\">$name";
   if (in_array($name, $admins)) echo " (admin)";
   echo "</option>\n";
  }
?>
                </select>
                <button type="submit" onclick="this.form.submit();"><?php echo gettext("remove user"); ?></button>
              </form>
            </div>
          </li>
          <li>
            <div onclick="toggleBox('modifyAdminBox');"><a><?php echo gettext("manage admins"); ?></a></div>
            <div id="modifyAdminBox" class="userMenuBox">
              <form id="changeAdmins" action="index.php?dir=<?php echo $path; ?>" method="post" accept-charset="UTF-8" enctype="multipart/form-data">
                <input type="hidden" name="job" value="changeAdmin">
                <select name="name" style="float: left;">
<?php
foreach ($user as $key => $name)
  {
   if (in_array($name, $admins)) echo "                  <option value=\"$name\">$name (admin)</option>\n";
   else                          echo "                  <option value=\"$name\">$name</option>\n";
  }
?>
                </select>
                <div style="float: left;">
                  <input id="addAdmin" type="radio" name="operation" value="add"><label for="addAdmin"><?php echo gettext("make admin"); ?></label><br>
                  <input id="removeAdmin" type="radio" name="operation" value="remove"><label for="removeAdmin"><?php echo gettext("make user"); ?></label>
		</div>
                <button type="submit" style="float: right;">OK</button>
                <div style="clear: both;"></div>
	      </form>
            </div>
          </li>
          <li>
            <div onclick="document.getElementById('viewLog').submit();"><a><?php echo gettext("view log"); ?></a></div>
            <form id="viewLog" action=".log" target="_blank" method="post" accept-charset="UTF-8" enctype="multipart/form-data"></form>
          </li>
	</ul>
	<hr>
	<ul class="userSettings"><?php } ?>
          <li>
            <div onclick="document.getElementById('logout').submit();">
            <form id="logout" action="https://guest@<?php echo $_SERVER["SERVER_NAME"] . str_replace("index.php", "", $_SERVER["SCRIPT_NAME"]); ?>.logout.php" method="post" accept-charset="UTF-8" enctype="multipart/form-data">
              <input type="hidden" name="job" value="logout">
              <input type="hidden" name="name" value="<?php echo $_SERVER['REMOTE_USER']; ?>">
              <a><?php echo gettext("log out"); ?></a>
            </form></div>
          </li>
        </ul>
      </div>
    </div>

    <div class="topSpacer"></div>
    <div class="shadow" style="margin: 10px; padding: 5px 10px;">
    <p id="fileCommandsHeaderBox" onclick="toggleBox('fileCommandsWrapper');"><b><?php echo gettext("file commands"); ?></b></p>
    <div id="fileCommandsWrapper" style="display: none;">
    <div id="rename">
      <form id="renameFile" action="index.php?dir=<?php echo $path; ?>" method="post" accept-charset="UTF-8" enctype="multipart/form-data">
        <input type="hidden" name="job" value="rename">
        <label for="newFilename"><?php echo gettext("Enter new file name:"); ?></label>
	<input id="oldFilename" type="hidden" name="oldFilename" value="">
	<input id="newFilename" type="text" name="newFilename" value="">
	<button type="button" onclick="this.form.submit();"><?php echo gettext("rename File"); ?></button>
      </form>
    </div>

    <div class="commandBox">
      <b><?php echo gettext("Upload file:"); ?></b><?php echo gettext("max 90 MiB!"); ?>
      <form id="uploadfile" class="createFiles" action=".uploadfile.php?dir=<?php echo $path; ?>" method="post" accept-charset="UTF-8" enctype="multipart/form-data">
	<input type="hidden" name="MAX_FILE_SIZE" value="100000000">
	<input type="file" name="files[]" id="fileToUpload" multiple="multiple" onchange="fileSelected();">
	<input type="button" onclick="uploadFile()" value="OK">
	<div id="fileName"></div>
	<div id="fileSize"></div>
	<div id="fileType"></div>
	<div id="progressNumber"></div>
	<div id="progress">
	  <div id="progressbar"></div>
	</div>
      </form>
    </div>
    <div class="commandBox">
      <b><?php echo gettext("Create dir:"); ?></b>
      <form id="createDir" class="createFiles" action="index.php?dir=<?php echo $path; ?>" method="post" accept-charset="UTF-8" enctype="multipart/form-data">
        <input type="hidden" name="job" value="createDir">
	<input type="hidden" name="path" value="<?php echo $path; ?>">
	<input type="text" name="newDir" id="newDir">
	<input type="button" onclick="this.form.submit();" value="OK">
      </form>
    </div>
<?php
$dir = scandir("$cwd/$path");
sort($dir, SORT_NATURAL | SORT_FLAG_CASE);
$time = time();
$c = 0;
$d = 0;

foreach ($dir as $key => $filename) // sort by dirs and files
  {
   if (strcmp($filename,".") == 0 or strcmp($filename, "..") == 0)
     {
      unset($dir[$key]);
      continue;
     }

   if (is_dir("$cwd/$path/$filename"))
     {
      $dirs[$d] = $filename;
      unset($dir[$key]);
      $d++;
      continue;
     }
  }
?>
    <form id="fileCommand" action="index.php?dir=<?php echo $path; ?>" method="post" accept-charset="UTF-8" enctype="multipart/form-data">
      <input id="job" type="hidden" name="job" value="">
      <div class="commandBox">
        <label for="moveTo"><?php echo gettext("Move to:"); ?></label>
        <select name="moveTo" id="moveTo">
          <?php recurseFolderStructure($FolderStructure); ?>
        </select>
        <button type="submit" onclick="switchJob('move');"><?php echo gettext("Move selected files"); ?></button>
      </div>

      <div class="commandBox" id="downloadZip" title="<?php echo gettext("Download selected files as zip archive."); ?>">
	<button type="button" onclick="switchJob('downloadZip');"><?php echo gettext("Download selected as zip"); ?></button> <?php echo gettext("max 90 MiB!"); ?>
      </div>

      <div class="commandBox" id="deleteFile" title="<?php echo gettext("Careful!!! Can NOT be restored!"); ?>">
        <button type="button" onclick="if (DeleteCheck() == true) { switchJob('del'); }"><?php echo gettext("Delete selected files"); ?></button>
      </div>
      </div> <!-- end fileCommandsWrapper -->
      <div style="clear: both;"></div>
      </div>

      <div class="shadow" id="fileTableWrapper">
        <table border="0" id="fileTable">
          <tr><th>#</th><th><?php echo gettext("status"); ?></th><th><?php echo gettext("filename (download)"); ?></th><th><?php echo gettext("size"); ?></th><th><?php echo gettext("date"); ?></th><th><?php echo gettext("file commands"); ?></th></tr>
          <tr class="hilight" style="background-color: #FFFFFF;">
            <td></td>
            <td></td>
            <td>
              <?php if ($path != $forward) 
                { ?><a href="index.php?dir=<?php echo $path . "/.."; ?>">..</a><?php }
	      ?>
	    </td>
	    <td></td>
	    <td></td>
	    <td>
	      <input type="checkbox" name="masterCheckboxFile" id="masterCheckboxFile" title="<?php echo gettext("mark all files"); ?>" onchange="toggleCheckboxFile(); toggleBox('fileCommandsWrapper');">
	    </td>
	  </tr>
<?php

$c = 0;
$totalFileSize = 0;
$bg = "#FFFFFF";
if ($path != "") $path .= "/";
if (!isset($dirs)) $dirs = array();
foreach ($dirs as $key => $filename)
  {
   $c++;
   if ($bg == "#FFFFFF") $bg = "#EEEEEE"; else $bg = "#FFFFFF";
   $filetime = filectime("$cwd/$path$filename");

   $filesize = filesize("$cwd/$path$filename");
   $totalFileSize = $totalFileSize + $filesize;

   $fs = convertFileSize($filesize);
   $filesize = $fs[0];
   $unit = $fs[1];

   $completed = "<img src=\".icons/directory.png\" alt=\"folder\" class=\"icon\" title=\"" . gettext("dir\nAccess: ") . getFilePerms("$cwd/$path$filename") . "\">";
   $filename_encoded = rawurlencode($filename);
   $string = "$completed</td><td> <a href=\"index.php?dir=$path$filename_encoded\">$filename</a>";
   $rename = "<div id=\"$c\" data-filename=\"$filename\" style=\"display: none;\"></div><a href=\"javascript:renameFile($c);\">" . gettext("rename") . "</a>";
   $move = "<input type=\"checkbox\" name=\"filename[]\" value=\"$filename\" class=\"checkboxFile\">";
   printf ("          <tr class=\"hilight\" style=\"background-color: $bg;\"><td>%02d</td><td style=\"text-align: center;\"> %s </td><td>(%.2f %s)</td><td>%s</td><td>%s | %s</td></tr>\n", $c, $string, $filesize, $unit, date("Y-m-d H:i:s",$filetime), $rename, $move);
  }

if (!isset($dir)) $dir = array();
foreach ($dir as $key => $filename)
  {
   $c++;
   if ($bg == "#FFFFFF") $bg = "#E5ECF2"; else $bg = "#FFFFFF";
   $filetime = filectime("$cwd/$path$filename");

   $filesize = filesize("$cwd/$path$filename");
   $totalFileSize = $totalFileSize + $filesize;

   $fs = convertFileSize($filesize);
   $filesize = $fs[0];
   $unit = $fs[1];

   $tooltip = "\n\n" . gettext("size:") . " ";
   if ($unit == "Byte") $tooltip .= $filesize;
   else $tooltip .= number_format($filesize, 2, ".", "");
   $tooltip .= " $unit\n" . gettext("Access: ") . getFilePerms("$cwd/$path$filename");

   if ($time > $filetime + 3) // Wait 3 secs more, to allow for cache-bursts and such...
     {
      $status = gettext("file - completely transferred");

      $completed = "<img src=\".icons/file.png\" alt=\"folder\" class=\"icon\" title=\"{$status}$tooltip\">";
      $filename_encoded = rawurlencode($filename);
      $string = "$completed</td><td title=\"" . gettext("open file") . "\"> <a href=\"{$path}$filename_encoded\" target=\"_blank\">$filename</a>";
      $rename = "<div id=\"$c\" data-filename=\"$filename\" style=\"display: none;\"></div><a href=\"javascript:renameFile($c);\">" . gettext("rename") . "</a>";
      $move = "<input type=\"checkbox\" name=\"filename[]\" value=\"$filename\" class=\"checkboxFile\">";
      $downloadFile = "<a href=\"$path$filename_encoded\" download>" . gettext("download") . "</a>";
      $fileCommands = "$rename | $move | $downloadFile";
     }
   else
     {
      $status = gettext("file in transfer...");
      $completed = "<span title=\"{$status}$tooltip\">&#8646;</span>";
      $string = "$completed </td><td> $filename";
      $rename = "";
      $move ="";
      $openFile = "";
      $fileCommands = "<span class=\"inTransfer\">$status</span>";
     }

   printf ("          <tr class=\"hilight\" style=\"background-color: $bg;\"><td>%02d</td><td style=\"text-align: center;\"> %s </td><td>(%.2f %s)</td><td>%s</td><td>%s</td></tr>\n", $c, $string, $filesize, $unit, date("Y-m-d H:i:s",$filetime), $fileCommands);
  }
?>
	</table>
      </div>
    </form>
  </div> <!-- end div id="main" -->
  <div id="footer">
    <b>
<?php 
$totalUnit = 0;

$totalfs = convertFileSize($totalFileSize);
$totalFileSize = $totalfs[0];
$totalUnit = $totalfs[1];

$end = microtime(true);
$proctime = $end - $start;
$unit = 0;

while ($proctime < 1) 
  {
   $proctime = $proctime * 1000;
   $unit++;
  }
switch ($unit)
  {
   case 0:
     $unit = "sec";
     break;
   case 1:
     $unit = "msec";
     break;
   case 2:
     $unit = "µsec";
     break;
  }

printf("      %.2f %s in %d %s - %s - %s %.3f %s", $totalFileSize, $totalUnit, $c, gettext("files"), date("d.F Y H:i:s", $time), gettext("processing needed"), $proctime, $unit);
?>
    </b>
  </div>
  <div id="errorMessages">
<?php
    if ($debug and count($error) > 0)
      {
       echo "Errors:"; foreach ($error as $key => $value) { echo ", $key"; } echo "<br>\n";
       echo "<b>\$phpErrorMsg:</b><br>\n<pre>$phpErrorMsg</pre>\n";
       writeLog("index.php: main(1037)");
      }

if ($debug and isset($debugs))
  {
   echo "<br>\n<b>Debug:</b><br>\n"; foreach ($debugs as $key => $value)
     {
      if ($key == "relPath") echo "<pre>$value</pre>\n";
      else echo "$key =&gt; $value<br>\n";
     }
  }
if ($debug and isset($_POST["job"])) { echo "<br>\n<b>_POST:</b><br>\n"; foreach ($_POST as $key => $value) { echo "$key =&gt; $value<br>\n"; } }
if ($debug and isset($_POST["job"]) and isset($_POST["filename"]) and is_array($_POST["filename"]))
  {
   echo "<br>\n<b>\$_POST[\"filename\"]:</b> <br>\n";
   foreach ($_POST["filename"] as $key => $value) echo "[$key] => $value<br>\n";
  }
?>
  </div>
  <?php if ($debug) { ?><pre><?php print_r($error["debug"]); ?></pre><?php } ?>
</body>
</html>