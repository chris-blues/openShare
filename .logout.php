<!DOCTYPE html>
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
setlocale(LC_MESSAGES, $locale);
bindtextdomain($domain, $directory);
textdomain($domain);
bind_textdomain_codeset($domain, 'UTF-8');
?>
<html>
<head>
<meta charset="UTF-8">
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<link rel="stylesheet" href=".style.css" type="text/css">
</head>
<body>
  <div id="main" style="text-align: center;">
    <?php
    $path = str_replace(".logout.php", "", $_SERVER["SCRIPT_NAME"]);
    echo gettext("<h1>Good bye {$_POST["name"]}!</h1>\n
    You have been logged out!\n
    <a href=\"{$_SERVER["HTTP_X_FORWARDED_PROTO"]}://{$_SERVER["HTTP_HOST"]}{$path}index.php\">\n
      Return to login\n
    </a>"); ?>
  </div>
</body>
</html>