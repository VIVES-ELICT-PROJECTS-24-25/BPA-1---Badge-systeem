<?php
session_start();
require_once 'config.php';

// Redirect logged in users
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: adminv1.php");
    exit;
}

$token = isset($_GET['token']) ? $_GET['token'] : '';
$error = '';
$success = '';
$token_valid = false;
$user_id = null;

if (empty($token)) {
    $error = "Ongeldige reset link. Vraag een nieuwe reset link aan.";
} else {
    $conn = getConnection();
    
    // Check if token exists and is valid
    $now = date('Y-m-d H:i:s');
    $check_query = "SELECT pr.User_ID, g.Voornaam FROM Password_Reset pr 
                    JOIN Gebruiker g ON pr.User_ID = g.User_ID 
                    WHERE pr.Token = ? AND pr.Expires > ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ss", $token, $now);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error = "Deze reset link is ongeldig of verlopen. Vraag een nieuwe reset link aan.";
    } else {
        $user = $result->fetch_assoc();
        $user_id = $user['User_ID'];
        $user_name = $user['Voornaam'];
        $token_valid = true;
    }
    
    $check_stmt->close();
    
    // Process password reset form
    if ($_SERVER["REQUEST_METHOD"] == "POST" && $token_valid) {
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        // Validate password
        if (empty($password)) {
            $error = "Voer een wachtwoord in.";
        } elseif (strlen($password) < 6) {
            $error = "Wachtwoord moet minstens 6 tekens bevatten.";
        } elseif ($password !== $confirm_password) {
            $error = "Wachtwoorden komen niet overeen.";
        } else {
            // Update password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_query = "UPDATE Gebruiker SET WW = ? WHERE User_ID = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                // Delete token after successful password reset
                $delete_query = "DELETE FROM Password_Reset WHERE User_ID = ?";
                $delete_stmt = $conn->prepare($delete_query);
                $delete_stmt->bind_param("i", $user_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                $success = "Je wachtwoord is succesvol gewijzigd. Je kunt nu inloggen met je nieuwe wachtwoord.";
                $token_valid = false; // Hide the form
            } else {
                $error = "Er is een fout opgetreden bij het wijzigen van je wachtwoord.";
            }
            
            $update_stmt->close();
        }
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nieuw Wachtwoord - MaakLab Badge Systeem</title>
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
        .reset-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
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
        .success-message {
            background-color: rgba(46, 204, 113, 0.1);
            color: #27ae60;
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
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        .login-link a {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        small {
            color: #7f8c8d;
            font-size: 0.85rem;
            display: block;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="header">
            <h1><i class="fas fa-lock"></i> Nieuw Wachtwoord</h1>
            <p>Stel je nieuwe wachtwoord in</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message"><?php echo $success; ?></div>
            <div class="login-link">
                <a href="login.php">Ga naar inloggen</a>
            </div>
        <?php elseif ($token_valid): ?>
            <form method="post" action="reset_token.php?token=<?php echo htmlspecialchars($token); ?>">
                <div class="form-group">
                    <label for="password">Nieuw wachtwoord</label>
                    <input type="password" id="password" name="password" required>
                    <small>Minimaal 6 tekens</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Bevestig wachtwoord</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit">Wachtwoord wijzigen</button>
            </form>
        <?php else: ?>
            <div class="login-link">
                <a href="password_reset.php">Terug naar wachtwoord reset</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>