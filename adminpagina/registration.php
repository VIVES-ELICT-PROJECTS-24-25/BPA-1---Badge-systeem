<?php
session_start();
require_once 'config.php';

// Controleer of de gebruiker al ingelogd is
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: adminv1.php");
    exit;
}

$error = '';
$success = '';

// Verwerk het registratieformulier
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Valideer input
    $voornaam = trim($_POST['voornaam']);
    $naam = trim($_POST['naam']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $telnr = trim($_POST['telnr']);
    $vives_nr = trim($_POST['vives_nr']);
    
    // Validatie
    if (empty($voornaam) || empty($naam) || empty($email) || empty($password)) {
        $error = "Vul alle verplichte velden in.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Ongeldig e-mailadres.";
    } elseif (strlen($password) < 6) {
        $error = "Wachtwoord moet minstens 6 tekens bevatten.";
    } elseif ($password !== $confirm_password) {
        $error = "Wachtwoorden komen niet overeen.";
    } else {
        // Controleer of e-mail al in gebruik is
        $conn = getConnection();
        $check_query = "SELECT User_ID FROM Gebruiker WHERE Email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Dit e-mailadres is al in gebruik.";
        } else {
            // Maak een Vives-record aan als er een Vives nummer is opgegeven
            $vives_info_id = null;
            if (!empty($vives_nr)) {
                // Controleer eerst of dit Vives nummer al bestaat
                $check_vives_query = "SELECT Vives_info_id FROM Vives WHERE Vives_nr = ?";
                $check_vives_stmt = $conn->prepare($check_vives_query);
                $check_vives_stmt->bind_param("s", $vives_nr);
                $check_vives_stmt->execute();
                $check_vives_result = $check_vives_stmt->get_result();
                
                if ($check_vives_result->num_rows > 0) {
                    $vives_row = $check_vives_result->fetch_assoc();
                    $vives_info_id = $vives_row['Vives_info_id'];
                } else {
                    // Maak nieuw Vives record
                    $richting = $_POST['richting'] ?? null;
                    $insert_vives_query = "INSERT INTO Vives (Vives_nr, Richting) VALUES (?, ?)";
                    $insert_vives_stmt = $conn->prepare($insert_vives_query);
                    $insert_vives_stmt->bind_param("ss", $vives_nr, $richting);
                    if ($insert_vives_stmt->execute()) {
                        $vives_info_id = $conn->insert_id;
                    }
                    $insert_vives_stmt->close();
                }
                $check_vives_stmt->close();
            }
            
            // Hash het wachtwoord
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $rol = 'student';  // Default rol voor nieuwe gebruikers
            $aanmaak_datum = date('Y-m-d');
            
            // Maak de gebruiker aan
            $insert_query = "INSERT INTO Gebruiker (Vives_Info_ID, Voornaam, Naam, Email, Telnr, WW, rol, Aanmaak_Acc) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("isssssss", 
                $vives_info_id, 
                $voornaam, 
                $naam, 
                $email, 
                $telnr, 
                $hashed_password, 
                $rol, 
                $aanmaak_datum
            );
            
            if ($insert_stmt->execute()) {
                $success = "Registratie succesvol! Je kunt nu inloggen.";
            } else {
                $error = "Er is een fout opgetreden bij het registreren: " . $conn->error;
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
    <title>Registratie - MaakLab Badge Systeem</title>
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
        .registration-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
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
            margin-bottom: 1.2rem;
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
        .form-row {
            display: flex;
            gap: 1rem;
        }
        .form-row .form-group {
            flex: 1;
        }
        .required:after {
            content: " *";
            color: #e74c3c;
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
    <div class="registration-container">
        <div class="header">
            <h1><i class="fas fa-user-plus"></i> Registreren</h1>
            <p>Maak een nieuw account aan voor het MaakLab Badge Systeem</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="post" action="registration.php">
            <div class="form-row">
                <div class="form-group">
                    <label for="voornaam" class="required">Voornaam</label>
                    <input type="text" id="voornaam" name="voornaam" required>
                </div>
                <div class="form-group">
                    <label for="naam" class="required">Achternaam</label>
                    <input type="text" id="naam" name="naam" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email" class="required">E-mailadres</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password" class="required">Wachtwoord</label>
                    <input type="password" id="password" name="password" required>
                    <small>Minimaal 6 tekens</small>
                </div>
                <div class="form-group">
                    <label for="confirm_password" class="required">Bevestig wachtwoord</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="telnr">Telefoonnummer</label>
                <input type="tel" id="telnr" name="telnr">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="vives_nr">Vives Nummer</label>
                    <input type="text" id="vives_nr" name="vives_nr" placeholder="bijv. R0996329">
                    <small>Alleen verplicht voor Vives-studenten</small>
                </div>
                <div class="form-group">
                    <label for="richting">Richting/Opleiding</label>
                    <input type="text" id="richting" name="richting">
                </div>
            </div>
            
            <button type="submit">Registreren</button>
            
            <div class="login-link">
                Heb je al een account? <a href="login.php">Inloggen</a>
            </div>
        </form>
    </div>
</body>
</html>