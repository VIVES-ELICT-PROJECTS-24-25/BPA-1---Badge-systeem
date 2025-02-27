<!DOCTYPE html>
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
        <p class="login-link">
            Nog geen account? <a href="registreer.php">Registreer hier</a>
        </p>
    </div>
</body>
</html>