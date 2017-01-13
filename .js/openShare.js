$(document).ready(function() {
    $("a.fancy").fancybox({
        'transitionIn'   :   'elastic',
        'transitionOut'  :   'elastic',
        'speedIn'        :   600,
        'speedOut'       :   400,
        'overlayShow'    :   true,
        'overlayOpacity' :    0.9,
        'overlayColor'   :   '#171410',
        'titlePosition'  :   'inside',
        'hideOnContentClick' : true,
        'cyclic' : 'true'
    });
});
</script>
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
