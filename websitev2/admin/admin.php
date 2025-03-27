<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Zorg ervoor dat we toegang hebben tot de config vanuit elke admin pagina
require_once dirname(__DIR__) . '/config.php';

// Controleer of gebruiker is ingelogd en een beheerder is
if (!isset($_SESSION['User_ID']) || !isset($_SESSION['Type']) || $_SESSION['Type'] != 'beheerder') {
    // Toon een error en redirect
    $_SESSION['error'] = 'Je hebt geen toegang tot het beheerdersgedeelte.';
    header('Location: ../index.php');
    exit;
}

// Voor debugging - verwijder in productie
// echo "<!-- Admin check passed: User_ID=" . $_SESSION['User_ID'] . ", Type=" . $_SESSION['Type'] . " -->";
?>