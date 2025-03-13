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

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservatie 3D printers - Login</title>
    <link rel="stylesheet" href="Styles/mystyle.css">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    
    <!-- Compiled and minified CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">

    <!-- Compiled and minified JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
       
    <script src="Scripts/auth.js"></script>
    <script src="Scripts/inlog.js"></script>
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <form id="loginForm">
            <label for="username">Gebruikersnaam:</label>
            <input type="text" id="username" required>
            
            <label for="password">Wachtwoord:</label>
            <input type="password" id="password" required>
            
            <button type="submit">Inloggen</button>
        </form>
    </div>
</body>
</html>
