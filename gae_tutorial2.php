<?php
/*************
*
*  Reimplemented the PHP Google App Enginge Tutorial in pEighthP to verify it works
*  Original code at https://developers.google.com/appengine/docs/php/gettingstarted/handlingforms
*
**************/
include('pEigthP.php');
?>
<html>
  <body>
    <? ob_start_peigthp(); ?>
    
      (if (array_key_exists 'content' _POST)
        (do (echo "You wrote:<pre>\n")
            (echo (htmlspecialchars (aget _POST 'content')))
            (echo "\n</pre>")))
            
    <? ob_end_peigthp(); ?>
    <form action="/sign" method="post">
      <div><textarea name="content" rows="3" cols="60"></textarea></div>
      <div><input type="submit" value="Sign Guestbook"></div>
    </form>
  </body>
</html>
