<?php
// This tells where the root folder of our share is. For example:
// if you uploaded these files to "example.com/share/*" then "data" will point
// to "example.com/share/data"
$forward = "data";

// These users will be able to add and remove other users. You can find the
// exact usernames in .htpasswd. Add users by making this line look sth like:
//
// $admins[] = "username";
// $admins[] = "someoneElse";
//
// or like this:
//
// $admins = array("username", "otheruser", "andSoOn");
//
$admins[] = "chris";
?>