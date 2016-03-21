<?php
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

$error = false;
$files = $_POST["files"];
$dir = realpath(getcwd() . "/" . $_GET["dir"]);
foreach ($_FILES["files"]["name"] as $key => $value)
  {
   $filename = "$dir/" . $_FILES["files"]["name"][$key];
   if (!move_uploaded_file($_FILES["files"]["tmp_name"][$key], $filename)) $error = true;
   chmod($filename,0755);
  }
if ($error) echo gettext("Error!\n");
else echo gettext("OK!\n");
?>