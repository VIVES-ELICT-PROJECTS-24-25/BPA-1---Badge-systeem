<?php
// Start the session
session_start();

// Unset all session variables
$_SESSION = array();

// If cookies are used, clear the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Set a session flash message that will persist through the redirect
session_start();
$_SESSION['just_logged_out'] = true;

// Store the logout info in a cookie as a backup mechanism
setcookie('lastLogout', time(), time() + 60, '/'); // Expires in 1 minute

// Redirect immediately to the login page
header("Location: index.php");
exit;
?>