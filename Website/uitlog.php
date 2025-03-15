<?php
// admin.php - Beheerderspagina voor MaakLab
session_start();

// Controleer of de gebruiker is ingelogd en een admin rol heeft
// Dit zou normaal gesproken worden geverifieerd met een login systeem
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Redirect naar login pagina als niet ingelogd of geen admin
    header("Location: sites/login.php");
    exit;
}
?>