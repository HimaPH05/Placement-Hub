<?php
session_start();
session_unset();     // remove all session variables
session_destroy();   // destroy session

header("Location: ../login.html"); // go back to login page
exit();
?>
