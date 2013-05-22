<?php
/*************
*
*  Reimplemented the PHP Google App Enginge Tutorial in pEighthP to verify it works
*  Original code at https://developers.google.com/appengine/docs/php/gettingstarted/usingusers
*
**************/

require_once('google/appengine/api/users/UserService.php');
include('pEigthP.php');
ob_start_peigthp();
?>
(use google\appengine\api\users\User)
(use google\appengine\api\users\UserService)

(def user (UserService::getCurrentUser))
(if user
  (echo "Hello, " (->getNickname user))
  (header (. "Location: " (UserService::createLoginURL (aget _SERVER "REQUEST_URI")))))

<? ob_end_peigthp(); ?>