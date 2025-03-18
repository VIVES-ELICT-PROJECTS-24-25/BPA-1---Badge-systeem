<?php
session_start();
require_once 'config.php';
require_once 'phpmailer_setup.php';

// Redirect logged in users
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: adminv1.php");
    exit;
}

$message = '';
$error = '';

// Process password reset request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Voer een e-mailadres in.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Ongeldig e-mailadres.";
    } else {
        $conn = getConnection();
        
        // Check if email exists
        $check_query = "SELECT User_ID, Email, Voornaam, Naam FROM Gebruiker WHERE Email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "Er is geen account gevonden met dit e-mailadres.";
        } else {
            $user = $result->fetch_assoc();
            $user_id = $user['User_ID'];
            $user_name = $user['Voornaam'];
            $full_name = $user['Voornaam'] . ' ' . $user['Naam'];
            
            // Generate unique token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // Token expires in 1 hour
            
            // Store token in database
            // First, check if there's an existing token and delete it
            $delete_query = "DELETE FROM Password_Reset WHERE User_ID = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param("i", $user_id);
            $delete_stmt->execute();
            $delete_stmt->close();
            
            // Insert new token
            $insert_query = "INSERT INTO Password_Reset (User_ID, Token, Expires) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("iss", $user_id, $token, $expires);
            
            if ($insert_stmt->execute()) {
                // Create the reset URL using the correct base URL
                $base_url = "https://3dprintersmaaklabvives.be/api_test/17_03/";
                $reset_url = $base_url . "reset_token.php?token=" . $token;
                
                // Create HTML email body
                $html_message = '
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            line-height: 1.6;
                            color: #333;
                        }
                        .container {
                            max-width: 600px;
                            margin: 0 auto;
                            padding: 20px;
                            border: 1px solid #ddd;
                        }
                        .header {
                            background-color: #3498db;
                            color: white;
                            padding: 10px 20px;
                            text-align: center;
                        }
                        .content {
                            padding: 20px;
                        }
                        .button {
                            display: inline-block;
                            background-color: #3498db;
                            color: white;
                            padding: 12px 25px;
                            text-decoration: none;
                            border-radius: 4px;
                            margin: 20px 0;
                        }
                        .footer {
                            margin-top: 30px;
                            text-align: center;
                            font-size: 12px;
                            color: #777;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>MaakLab Badge Systeem</h1>
                        </div>
                        <div class="content">
                            <h2>Wachtwoord Reset</h2>
                            <p>Beste ' . htmlspecialchars($full_name) . ',</p>
                            <p>We hebben een verzoek ontvangen om je wachtwoord te resetten voor het MaakLab Badge Systeem.</p>
                            <p>Klik op de onderstaande knop om je wachtwoord te resetten. Deze link is één uur geldig.</p>
                            <p style="text-align: center;">
                                <a href="' . htmlspecialchars($reset_url) . '" class="button">Reset mijn wachtwoord</a>
                            </p>
                            <p>Als je geen wachtwoord reset hebt aangevraagd, kun je deze e-mail veilig negeren.</p>
                            <p>Met vriendelijke groet,<br>MaakLab Badge Systeem Team</p>
                        </div>
                        <div class="footer">
                            <p>© ' . date('Y') . ' MaakLab Badge Systeem | Dit is een geautomatiseerd bericht, beantwoord deze e-mail niet.</p>
                        </div>
                    </div>
                </body>
                </html>
                ';
                
                // Also create a plain text version for email clients that don't support HTML
                $plain_message = 
                "Wachtwoord Reset - MaakLab Badge Systeem\n\n" .
                "Beste " . $full_name . ",\n\n" .
                "We hebben een verzoek ontvangen om je wachtwoord te resetten voor het MaakLab Badge Systeem.\n" .
                "Klik op de onderstaande link om je wachtwoord te resetten. Deze link is één uur geldig:\n\n" .
                $reset_url . "\n\n" .
                "Als je geen wachtwoord reset hebt aangevraagd, kun je deze e-mail veilig negeren.\n\n" .
                "Met vriendelijke groet,\nMaakLab Badge Systeem Team";
                
                // Send email using PHPMailer
                $emailResult = sendEmail(
                    $email,
                    "MaakLab Badge Systeem - Wachtwoord reset",
                    $html_message,
                    $plain_message
                );
                
                if ($emailResult === true) {
                    $message = "Een wachtwoord reset link is verstuurd naar " . htmlspecialchars($email) . 
                              ". Controleer je inbox en volg de instructies in de e-mail.";
                } else {
                    $error = "Er is een probleem opgetreden bij het versturen van de e-mail: " . $emailResult;
                    // Log the error for debugging (in a real application)
                    error_log("Email sending failed: " . $emailResult);
                }
            } else {
                $error = "Er is een fout opgetreden. Probeer het later opnieuw.";
            }
            
            $insert_stmt->close();
        }
        
        $check_stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wachtwoord Resetten - MaakLab Badge Systeem</title>
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
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="header">
            <h1><i class="fas fa-key"></i> Wachtwoord Resetten</h1>
            <p>Vul je e-mailadres in om je wachtwoord te resetten</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($message)): ?>
            <div class="success-message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (empty($message)): ?>
            <form method="post" action="password_reset.php">
                <div class="form-group">
                    <label for="email">E-mailadres</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <button type="submit">Wachtwoord reset link versturen</button>
            </form>
        <?php endif; ?>
        
        <div class="login-link">
            <a href="login.php">Terug naar inloggen</a>
        </div>
    </div>
</body>
</html>