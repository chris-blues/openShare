<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<title>share <?php echo $_SERVER["SERVER_NAME"]; ?></title>
</head>
<?php
//$debug = false;
$debug = true;

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

if ($_POST["job"] == "saveFile")
  {
   file_put_contents($_POST["file"], $_POST["filecontent"]);
  }

$file = file_get_contents($_POST["file"]);

?>
<body>
  <h1><?php echo $_POST["file"]; ?></h1>
  <form id="viewFile" action="view.php" method="post" accept-charset="UTF-8" enctype="multipart/form-data">
    <button type="submit"><?php echo gettext("Save file"); ?></button>
    <input type="hidden" name="job" value="saveFile">
    <button type="button" onclick="window.close();"><?php echo gettext("Close file"); ?></button>
    <input type="hidden" name="file" value="<?php echo $_POST["file"]; ?>">
    <textarea form="viewFile" name="filecontent" autofocus style="width: 99%; height: 750px;"><?php echo htmlspecialchars($file, ENT_COMPAT | ENT_HTML5, "UTF-8"); ?></textarea>
  </form>
</body>
</html>