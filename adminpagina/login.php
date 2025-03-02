<?php
session_start();

// Controleer of de gebruiker al ingelogd is
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: adminv1.php");
    exit;
}

// Verwerk de login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Hardcoded login gegevens
    if ($username === 'admin' && $password === 'admin') {
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $username;
        header("Location: adminv1.php"); // Stuur door naar adminpagina
        exit;
    } else {
        $error = "Ongeldige gebruikersnaam of wachtwoord.";
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <h2>Login</h2>
    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
    <form method="post" action="login.php">
        <label for="username">Gebruikersnaam:</label>
        <input type="text" name="username" required><br>
        <label for="password">Wachtwoord:</label>
        <input type="password" name="password" required><br>
        <input type="submit" value="Inloggen">
    </form>
</body>
</html>