<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
</head>
<body>
  <div id="main" style="text-align: center;">
    <?php
    $path = str_replace(".logout.php", "", $_SERVER["SCRIPT_NAME"]);
    echo "<h1>Good bye {$_POST["name"]}!</h1>\n
    You have been logged out!\n
    <a href=\"{$_SERVER["HTTP_X_FORWARDED_PROTO"]}://{$_SERVER["HTTP_HOST"]}{$path}index.php\">\n
      Return to login\n
    </a>"; ?>
  </div>
</body>
</html>