<!DOCTYPE HTML>
<?php
error_reporting(E_ALL);
ini_set("display_errors", 0);
?>
<html>
<head>
  <meta http-equiv="content-type" content="text/html; charset=UTF-8">
  <meta name=viewport content="width=device-width, initial-scale=1">
  <title>cbPlayer - <?php echo $_GET["dir"]; ?></title>
  <meta name="author" content="chris_blues">
  <meta name="generator" content="brain 1.0 &amp; gedit 3.12.2 / kate 3.14.2">
  <link rel="stylesheet" href=".style.css" type="text/css">
  <link rel="stylesheet" href="../cbplayer/cbplayer.css" type="text/css">
</head>
<body>
<?php
$cbPlayer_dirname = "../cbplayer";
$cbPlayer_mediadir = $_GET["dir"];
$cbPlayer_showDownload = true;
$cbPlayer_showTimer = true;
$debug=true;
echo "  <h1>" . $cbPlayer_mediadir . "</h1>\n";
?>

  <?php include("../cbplayer/cbplayer.php"); ?>

</body>
</html>
