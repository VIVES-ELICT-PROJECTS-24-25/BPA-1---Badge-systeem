<?php
session_start();

// Controleer of de gebruiker al ingelogd is
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: adminv1.php");
    exit;
}

// Verwerk de login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once 'config.php';
    $conn = getConnection();
    
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    // Zoek gebruiker in database
    $stmt = $conn->prepare("SELECT User_ID, Voornaam, Email, WW, rol FROM Gebruiker WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verifieer wachtwoord
        if (password_verify($password, $user['WW'])) {
            // Wachtwoord is correct, start sessie
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user['User_ID'];
            $_SESSION['username'] = $user['Voornaam'];
            $_SESSION['email'] = $user['Email'];
            $_SESSION['role'] = $user['rol'];
            
            // Update laatste aanmeld timestamp
            $updateStmt = $conn->prepare("UPDATE Gebruiker SET Laatste_Aanmeld = NOW() WHERE User_ID = ?");
            $updateStmt->bind_param("i", $user['User_ID']);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Alleen admins mogen naar het admin dashboard
            if ($user['rol'] === 'admin') {
                header("Location: adminv1.php");
            } else {
                // Andere gebruikers naar een andere pagina sturen
                header("Location: userpage.php");
            }
            exit;
        } else {
            $error = "Ongeldig wachtwoord.";
        }
    } else {
        $error = "Geen gebruiker gevonden met dit e-mailadres.";
    }
    
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MaakLab Badge Systeem</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .header h1 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        .header p {
            color: #7f8c8d;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 600;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #bdc3c7;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        .error-message {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        button[type="submit"] {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s;
        }
        button[type="submit"]:hover {
            background-color: #2980b9;
        }
        .links-container {
            margin-top: 1.5rem;
            text-align: center;
        }
        .links-container a {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            margin: 0 10px;
        }
        .links-container a:hover {
            text-decoration: underline;
        }
        .forgot-link {
            text-align: right;
            margin-top: -1rem;
            margin-bottom: 1.5rem;
        }
        .forgot-link a {
            color: #7f8c8d;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .forgot-link a:hover {
            color: #3498db;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="header">
            <h1><i class="fas fa-sign-in-alt"></i> Login</h1>
            <p>Log in op het MaakLab Badge Systeem</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="post" action="login.php">
            <div class="form-group">
                <label for="email">E-mailadres</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Wachtwoord</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="forgot-link">
                <a href="password_reset.php">Wachtwoord vergeten?</a>
            </div>
            
            <button type="submit">Inloggen</button>
            
            <div class="links-container">
                <a href="registration.php">Registreren</a>
            </div>
        </form>
    </div>
</body>
</html>