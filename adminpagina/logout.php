<?php
session_start();

// Verwijder alle sessie variabelen
$_SESSION = array();

// Vernietig de sessie cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Vernietig de sessie
session_destroy();

// Redirect naar login pagina
header("Location: login.php");
exit;
?>