<?php
session_start();
require_once 'config.php';

// Gebruiker 'inactive' maken in database
if (isset($_SESSION['User_ID'])) {
    try {
        $stmt = $conn->prepare("UPDATE User SET HuidigActief = 0 WHERE User_ID = ?");
        $stmt->execute([$_SESSION['User_ID']]);
    } catch (PDOException $e) {
        // Fout stil negeren, we loggen sowieso uit
    }
}

// Sessie vernietigen
session_unset();
session_destroy();

// Redirect naar homepage
header('Location: index.php');
exit;
?>