<?php
/**
 * Global configuration file
 * 
 * Should be included at the beginning of every page
 */

// ABSOLUTE FIX voor debug output probleem
ini_set('output_buffering', 'on');

// Capture all output
ob_start(function($output) {
    // Remove the debug output
    return preg_replace('/Current Date and Time \(UTC - YYYY-MM-DD HH:MM:SS formatted\): [0-9\-: ]+\s*Current User\'s Login: Piotr-0/s', '', $output);
});

// Sessie starten/hervatten
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Andere globale configuratie
date_default_timezone_set('Europe/Brussels');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Voor testdoeleinden - verwijder in productie
// $_SESSION['authenticated'] = true;
// $_SESSION['user_id'] = 4;
?>