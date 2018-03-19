<?php
// =============  FUNCTIONS  ===============

function writeLog ($calledFrom)
  {
   global $error, $phpErrorMsg;
   $handle = fopen(".log", "a");
     fwrite ($handle, date("Y-m-d H:i:s") . " - {$_SERVER["REMOTE_USER"]} - call: $calledFrom");
     if (isset($error) and count($error) > 0) { fwrite ($handle, " - errors: "); foreach ($error as $key => $value) fwrite ($handle, " $key"); }
     fwrite ($handle, " - " . $_SERVER["REQUEST_URI"]);
     foreach ($_POST as $key => $value)
       { // disguise passwords!
        if (stripos($key, "passwd") !== false)
          {
           $pl = strlen($value);
           $value = "";
           for ($i = 0; $i < $pl; $i++)
             { $value .= "*"; }
	   $value .= " ($pl)";
	  }
        fwrite ($handle, "\n\$_POST[$key] : $value");
       }
   fwrite($handle, "\n");
   if (strlen($phpErrorMsg) > 2) { fwrite ($handle, "$phpErrorMsg\n"); }
   fclose ($handle);
  }

function evaluateBool ($var)
  {
   switch ($var)
     {
      case true:  $res = "TRUE"; break;
      case false: $res = "FALSE"; break;
      default: 	  $res = $var;
     }
   return $res;
  }

function populateHtaccess ($pathHtpasswd)
  {
   $htaccess[] = "# generated by webspaceManager";
   $htaccess[] = "<ifmodule mod_rewrite.c>";
   $htaccess[] = "  RewriteEngine On";
   $htaccess[] = "  RewriteCond %{HTTPS} off";
   $htaccess[] = "  RewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]";
   $htaccess[] = "</ifmodule>";
   $htaccess[] = "<IfModule mod_expires.c>";
   $htaccess[] = "  ExpiresActive off";
   $htaccess[] = "  ExpiresDefault \"access plus 1 seconds\"";
   $htaccess[] = "  ExpiresByType text/css \"access plus 1 months\"";
   $htaccess[] = "  ExpiresByType font/* \"access plus 1 years\"";
   $htaccess[] = "</IfModule>";
   $htaccess[] = "AuthName \"Login\"";
   $htaccess[] = "AuthType Basic";
   $htaccess[] = "AuthUserFile $pathHtpasswd";
   $htaccess[] = "#AuthGroupFile /dev/null";
   $htaccess[] = "Require valid-user";
   $htaccess[] = "SetEnvIf Request_URI \"(.logout\.php)$\"  allow";
   $htaccess[] = "SetEnvIf Request_URI \"(join\.php)$\"  allow";
   $htaccess[] = "SetEnvIf Request_URI \"(.js/join\.js)$\" allow";
   $htaccess[] = "Order allow,deny";
   $htaccess[] = "Allow from env=allow";
   $htaccess[] = "Satisfy any";
   $htaccess[] = "Header add \"disablevcache\" \"true\"";
   return $htaccess;
  }

function getFilePerms ($filename)
  {
   clearstatcache();
   $perms = substr(decoct(fileperms($filename)),2);
   return($perms);
  }

function readDirStructure($path = "")
  {
   if ($path == "") { $thisFolder = getcwd(); $branch = ""; }
   else { $thisFolder = getcwd() . "/$path"; $branch = $path; }

   $folderContent = scandir($thisFolder);

   foreach ($folderContent as $key => $value)
     {
      $value = trim($value);
      $perms = getFilePerms("$thisFolder/$value");
      if ($perms != "0775") { chmod ("$thisFolder/$value", 0775); }

      if (!is_dir("$thisFolder/$value") or strncmp($value,".",1) == 0)
        {
         unset($folderContent[$key]);
	}
     }

   $c = 0;
   foreach ($folderContent as $key => $value)
     {
      if (count($folderContent) > 0)
        {
         if ($path == "") $next = $value;
         else $next = "$path/$value";
         $FolderStructure[$value] = readDirStructure("$next");
	}
     }

   return $FolderStructure;
  }

function recurseFolderStructure($path, $parent = "ROOT")
  {
   //echo "Start recurseFolderStructure($path, $depth, $parent);<br>\n";
   foreach ($path as $key => $value)
     {
      $tmp = explode("/", $parent);
      $depth = count($tmp);
      if ($parent == "ROOT") $depth = 0;
      for ($i = 0, $ph = ""; $i <= $depth; $i++)
        { $ph .= "&nbsp;&nbsp;"; }
      if ($parent == "ROOT") { echo "      <option value=\"$key\">$ph $key</option>\n"; $child = "$key"; }
      else { echo "      <option value=\"$parent/$key\">$ph $key</option>\n"; $child = "$parent/$key"; }
      if (is_array($value))
        {
         $depth++;
         recurseFolderStructure($value, $child);
	}
      else $depth--;
     }
  }

function convertFileSize ($filesize)
  {
   $unit = 0;
   while ($filesize > 1024)
     {
      $filesize = $filesize / 1024;
      $unit++;
     }

   switch ($unit)
     {
      case 0:
        $unit = "Byte";
        break;
      case 1:
        $unit = "kiB";
        break;
      case 2:
        $unit = "MiB";
        break;
      case 3:
        $unit = "GiB";
        break;
      case 4:
        $unit = "TiB";
        break;
     }
   return array($filesize, $unit);
  }

function cleanDir ($dir)
  {
   global $phpErrorMsg;
   $dirContents = scandir($dir);
   foreach ($dirContents as $key => $value)
     {
      if ($value == "." or $value == "..") continue;
      if (is_dir("$dir/$value"))
        {
         if (!rmdir("$dir/$value"))
           {
            cleanDir("$dir/$value");
            if (!rmdir("$dir/$value")) { $phpErrorMsg .= "Error deleting $dir/$value\n"; $error["rmDir"] = true; $error["debug"] = debug_backtrace(); }
	   }
         rmdir("$dir/$value");
        }
      else unlink("$dir/$value");
     }
  }

function scanForFiles($path)
  {
   $contents = scandir($path);
   foreach ($contents as $key => $value)
     {
      if ($value == "." or $value == "..") { unset($contents[$key]); continue; }
      if (is_dir("$path/$value"))
        {
         unset ($contents[$key]);
         $tmp = scanForFiles("$path/$value");
         foreach ($tmp as $filename) $contents[] = $filename;
	}
      else
        {
         $contents[$key] = "$path/$value";
	}
     }
   return $contents;
  }

function ZipStatusString( $status )
{
    switch( (int) $status )
    {
        case ZipArchive::ER_OK           : return 'N No error';
        case ZipArchive::ER_MULTIDISK    : return 'N Multi-disk zip archives not supported';
        case ZipArchive::ER_RENAME       : return 'S Renaming temporary file failed';
        case ZipArchive::ER_CLOSE        : return 'S Closing zip archive failed';
        case ZipArchive::ER_SEEK         : return 'S Seek error';
        case ZipArchive::ER_READ         : return 'S Read error';
        case ZipArchive::ER_WRITE        : return 'S Write error';
        case ZipArchive::ER_CRC          : return 'N CRC error';
        case ZipArchive::ER_ZIPCLOSED    : return 'N Containing zip archive was closed';
        case ZipArchive::ER_NOENT        : return 'N No such file';
        case ZipArchive::ER_EXISTS       : return 'N File already exists';
        case ZipArchive::ER_OPEN         : return 'S Can\'t open file';
        case ZipArchive::ER_TMPOPEN      : return 'S Failure to create temporary file';
        case ZipArchive::ER_ZLIB         : return 'Z Zlib error';
        case ZipArchive::ER_MEMORY       : return 'N Malloc failure';
        case ZipArchive::ER_CHANGED      : return 'N Entry has been changed';
        case ZipArchive::ER_COMPNOTSUPP  : return 'N Compression method not supported';
        case ZipArchive::ER_EOF          : return 'N Premature EOF';
        case ZipArchive::ER_INVAL        : return 'N Invalid argument';
        case ZipArchive::ER_NOZIP        : return 'N Not a zip archive';
        case ZipArchive::ER_INTERNAL     : return 'N Internal error';
        case ZipArchive::ER_INCONS       : return 'N Zip archive inconsistent';
        case ZipArchive::ER_REMOVE       : return 'S Can\'t remove file';
        case ZipArchive::ER_DELETED      : return 'N Entry has been deleted';

        default: return sprintf('Unknown status %s', $status );
    }
}

function create_zip ($files = array(), $destination)
  {
   global $path, $error, $debugs, $phpErrorMsg;
   foreach ($files as $key => $value)
     {
      if (is_dir("$path/$value"))
        {
         $file = scanForFiles("$path/$value");
         unset($files[$key]);
         foreach ($file as $value2) $files[] = "$value2";
        }
      else $files[$key] = "$path/$value";
     }

   // Create zip file
   $zip = new ZipArchive;
   $ressource = $zip->open("$destination", ZipArchive::CREATE | ZipArchive::OVERWRITE );
//    $ressource = $zip->open("$destination");
   if ($ressource === true)
     {
      $c = 0;
      foreach ($files as $value)
        {
         $datasize += filesize($value);
         if ($datasize > 100 * 1024 * 1024 or $c > 200)
           {
            //echo "datasize ($datasize > " . 100 * 1024 * 1024 . ") or c ($c > 200)<br>\n";
            $zip->close();
            $zip = new ZipArchive;
            $ressource = $zip->open("$destination.zip");
            $datasize = 0;
            $c = 0;
            //echo "continue zipping...<br>\n";
           }
	 $relpath = str_replace($path, "", $value);
         $zip->addFile($value, $relpath);
         $debugs["relPath"] .= "$value -&gt; $relpath\n";
         $c++;
	}
      $zip->close();
      return true;
     }
   else
     {
      $error["zipOpen"] = true;
      $phpErrorMsg .= "Error: zipfile could not be opened: " . ZipStatusString( $ressource ) . "\n";
      $error["debug"] = debug_backtrace();
      return false;
     }
  }

function writeHtpasswd ($fullFileName, $htpasswd)
  {
   global $phpErrorMsg;
   if (!$handle = fopen($fullFileName, "w"))
     { $phpErrorMsg .= "Error! Could not open $fullFileName\n"; $error["openHtpasswd"] = true; $error["debug"] = debug_backtrace(); }

   foreach ($htpasswd as $key => $value)
     {
      $tmp = explode(":", $value);
      $pwChk[] = $tmp[0];
     }
   foreach ($pwChk as $key => $value)
     {
      $match = array_search($value, $pwChk);
      if ($match !== false and $match != $key and $match <= $key)
        {
         if ($debug) { echo "Multiple entries for $value found. Removing first occurence.<br>\n"; }
         unset($htpasswd[$key]);
	}
     }
   foreach ($htpasswd as $line => $data)
     {
      if ($line > 0) fwrite($handle, "\n$data");
      else fwrite ($handle, "$data");
     }
   fclose ($handle);

   writeLog ("functions.php: writeHtpasswd($fullFileName)");
  }

function writeHtaccess ($fullFileName, $htaccess)
  {
   global $phpErrorMsg;
   if (!$handle = fopen ($fullFileName, "w"))
     { $phpErrorMsg .= "Error! Could not open $fullFileName\n"; $error["openHtaccess"] = true; $error["debug"] = debug_backtrace(); }
   foreach ($htaccess as $line => $data) { fwrite ($handle, "$data\n"); }
   fclose ($handle);

   writeLog ("functions.php: writeHtaccess($fullFileName)");
  }

function changeAdmin ($fullFileName, $name, $job)
  {
   global $phpErrorMsg, $rootPath, $debugs;
   $configFile = file($fullFileName, FILE_IGNORE_NEW_LINES);
   if (!$handle = fopen ($fullFileName, "w"))
     { $phpErrorMsg .= "Error! Could not open $fullFileName\n"; $error["openConfigPhp"] = true; $error["debug"] = debug_backtrace(); }
   fwrite ($handle, "<?php\n");
   foreach ($configFile as $line => $data)
     {
      if (strcmp($data, "<?php") == 0 or strcmp($data, "?>") == 0) continue; // we handle the php tags seperately
      // if we want to remove someone, skip writing him!
      if ($job == "remove") { if (strpos($data, $name) !== false) continue; }
      fwrite ($handle, "$data\n");
     }
   // if we want to add someone, add him! :o)
   if ($job == "add") fwrite($handle, "\$admins[] = \"$name\";\n");
   fwrite($handle, "?>");
   fclose ($handle);

   $logString = "functions.php: changeAdmin(" . str_replace("$rootPath/", "", $fullFileName) . ", $name, $job)";
   writeLog ($logString);
   $debugs["rootPath"] = $rootPath;
   $debugs["fullFileName"] = $fullFileName;
   $debugs["changeAdmin"] = $logString;
   $reload = true;
  }

function checkQueue ($email, $hash, $path)
  {
   $emailVerified = false;
   $queue = file($path);
   foreach ($queue as $key => $value)
     {
      $value = trim($value);
      if (strncmp($value, $email, strlen($email)) == 0)
        {
         //unset ($queue[$key]);
         $tmp = explode (" ", trim($value));
         if (strcmp($tmp[1], $hash) == 0) { $emailVerified = true; echo "hash matches...<br>\n"; }
        }
     }
   return $emailVerified;
  }

function cleanUpQueue ($email, $path)
  {
   $queue = file($path);
   foreach ($queue as $key => $value)
     {
      $value = trim($value);
      if (strncmp($value, $email, strlen($email)) == 0)
        {
         unset($queue[$key]);
        }
     }
   $handle = fopen ($path, "w");
   foreach ($queue as $key => $value) fwrite($handle, $value . "\n");
   fclose ($handle);
  }

function reloadIndex ()
  { ?>
<html>
<head>
<meta charset="UTF-8">
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<title>share <?php echo $_SERVER["SERVER_NAME"]; ?></title>
<link rel="stylesheet" href=".style.css" type="text/css">
<meta http-equiv="refresh" content="0; URL=index.php">
</head>
<body>
  <h1><a href="index.php"><?php echo gettext("Going in!"); ?></a></h1>
</body>
</html>
<?php }

// =============  FUNCTIONS  ===============
?>
