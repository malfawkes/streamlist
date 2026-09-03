<?php
// logout.php — wipe the session, go home

require_once 'includes/auth.php';   // boots session (must be running to destroy it)

 $_SESSION = [];      // empty the locker
session_destroy();   // remove the locker

header('Location: index.php');
exit;