<?php
// Sign out: start the session first (the old file never did, so session_destroy() did nothing),
// wipe it, delete the cookie, then reload the home page as a visitor.
session_start();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Location: reserve.php?out=1');
exit;
