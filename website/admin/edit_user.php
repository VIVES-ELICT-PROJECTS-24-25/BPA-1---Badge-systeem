<?php
// Admin toegang controle
require_once 'admin.php';

$pageTitle = 'Gebruiker bewerken - 3D Printer Reserveringssysteem';
$currentPage = 'admin-users';

// Verwerken van fouten/succes
$successMessage = '';
$errorMessage = '';

// Controleer of een gebruikers-ID is opgegeven
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: users.php');
    exit;
}

$userId = $_GET['id'];

// Gebruikersgegevens ophalen
try {
    $stmt = $conn->prepare("
        SELECT User_ID, Voornaam, Naam, Emailadres, Telefoon, Type, HulpNodig
        FROM User 
        WHERE User_ID = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // Gebruiker niet gevonden
        header('Location: users.php');
        exit;
    }
    
    // Ophalen van VIVES-specifieke gegevens indien het een student of docent is
    if ($user['Type'] == 'student' || $user['Type'] == 'docent') {
        $stmt = $conn->prepare("
            SELECT Vives_ID, opleiding_id, Type as Vives_Type
            FROM Vives
            WHERE User_ID = ?
        ");
        $stmt->execute([$userId]);
        $vivesInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Alle opleidingen ophalen voor select-opties
    $stmt = $conn->prepare("SELECT id, naam FROM opleidingen ORDER BY naam");
    $stmt->execute();
    $opleidingen = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $errorMessage = 'Fout bij ophalen van gebruikersgegevens: ' . $e->getMessage();
}

// Verwerk het formulier bij indienen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voornaam = trim($_POST['voornaam'] ?? '');
    $naam = trim($_POST['naam'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefoon = trim($_POST['telefoon'] ?? '');
    $type = $_POST['type'] ?? $user['Type'];
    $vives_id = trim($_POST['vives_id'] ?? '');
    $opleiding_id = $_POST['opleiding_id'] ?? '';
    $hulpNodig = isset($_POST['hulp_nodig']) ? (int)$_POST['hulp_nodig'] : 1;
    
    // Basisvalidatie
    if (empty($voornaam) || empty($naam) || empty($email)) {
        $errorMessage = 'Voornaam, naam en e-mail zijn verplicht.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Ongeldig e-mailadres.';
    } else {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Controleer of e-mail al gebruikt wordt door een andere gebruiker
            $stmt = $conn->prepare("SELECT User_ID FROM User WHERE Emailadres = ? AND User_ID != ?");
            $stmt->execute([$email, $userId]);
            
            if ($stmt->rowCount() > 0) {
                $errorMessage = 'Dit e-mailadres is al in gebruik door een andere gebruiker.';
            } else {
                // Update gebruikersgegevens
                $stmt = $conn->prepare("
                    UPDATE User SET 
                    Voornaam = ?, 
                    Naam = ?, 
                    Emailadres = ?, 
                    Telefoon = ?, 
                    Type = ?,
                    HulpNodig = ?
                    WHERE User_ID = ?
                ");
                
                $stmt->execute([
                    $voornaam,
                    $naam,
                    $email,
                    $telefoon,
                    $type,
                    $hulpNodig,
                    $userId
                ]);
                
                // Update VIVES gegevens indien van toepassing
                if ($type == 'student' || $type == 'docent') {
                    $vivesType = ($type == 'student') ? 'student' : 'medewerker';
                    
                    // Controleer of de VIVES record al bestaat
                    $stmt = $conn->prepare("SELECT User_ID FROM Vives WHERE User_ID = ?");
                    $stmt->execute([$userId]);
                    
                    if ($stmt->rowCount() > 0) {
                        // Update bestaande record
                        $stmt = $conn->prepare("
                            UPDATE Vives SET 
                            Voornaam = ?, 
                            Vives_id = ?, 
                            opleiding_id = ?, 
                            Type = ?
                            WHERE User_ID = ?
                        ");
                        
                        $stmt->execute([
                            $voornaam,
                            $vives_id,
                            ($type == 'student') ? $opleiding_id : null,
                            $vivesType,
                            $userId
                        ]);
                    } else {
                        // Maak nieuwe record aan
                        $stmt = $conn->prepare("
                            INSERT INTO Vives (User_ID, Voornaam, Vives_id, opleiding_id, Type)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $userId,
                            $voornaam,
                            $vives_id,
                            ($type == 'student') ? $opleiding_id : null,
                            $vivesType
                        ]);
                    }
                }
                
                // Wachtwoord bijwerken als er een nieuw is opgegeven
                if (!empty($_POST['new_password'])) {
                    $newPassword = $_POST['new_password'];
                    $confirmPassword = $_POST['confirm_password'];
                    
                    if ($newPassword !== $confirmPassword) {
                        $errorMessage = 'Wachtwoorden komen niet overeen.';
                    } elseif (strlen($newPassword) < 6) {
                        $errorMessage = 'Wachtwoord moet minimaal 6 tekens bevatten.';
                    } else {
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        
                        $stmt = $conn->prepare("UPDATE User SET Wachtwoord = ? WHERE User_ID = ?");
                        $stmt->execute([$hashedPassword, $userId]);
                    }
                }
                
                if (empty($errorMessage)) {
                    $conn->commit();
                    $successMessage = 'Gebruiker is succesvol bijgewerkt.';
                    
                    // Gebruikersgegevens opnieuw ophalen
                    $stmt = $conn->prepare("
                        SELECT User_ID, Voornaam, Naam, Emailadres, Telefoon, Type, HulpNodig
                        FROM User 
                        WHERE User_ID = ?
                    ");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user['Type'] == 'student' || $user['Type'] == 'docent') {
                        $stmt = $conn->prepare("
                            SELECT Vives_ID, opleiding_id, Type as Vives_Type
                            FROM Vives
                            WHERE User_ID = ?
                        ");
                        $stmt->execute([$userId]);
                        $vivesInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                } else {
                    $conn->rollBack();
                }
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            $errorMessage = 'Fout bij bijwerken van gebruiker: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="d-flex justify-content-center mb-4">
                        <a href="../index.php" class="text-white text-decoration-none">
                            <span class="fs-4">3D Printer Admin</span>
                        </a>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="users.php">
                                <i class="fas fa-users me-2"></i>
                                Gebruikers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="printers.php">
                                <i class="fas fa-print me-2"></i>
                                Printers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reservations.php">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Reserveringen
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="filaments.php">
                                <i class="fas fa-layer-group me-2"></i>
                                Filament
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="openingsuren.php">
                                <i class="fas fa-clock me-2"></i>
                                Openingsuren
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="opleidingen.php">
                                <i class="fas fa-graduation-cap me-2"></i>
                                Opleidingen
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="feedback.php">
                                <i class="fas fa-comments me-2"></i>
                                Feedback
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-home me-2"></i>
                                Terug naar site
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Uitloggen
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gebruiker bewerken</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="users.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Terug naar gebruikersoverzicht
                        </a>
                    </div>
                </div>
                
                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $successMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $errorMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-user-edit me-1"></i>
                        Gebruikersgegevens bewerken
                    </div>
                    <div class="card-body">
                        <form method="post" action="edit_user.php?id=<?php echo $userId; ?>">
                            <div class="row mb-3">
                                <label class="col-md-3 col-form-label">Gebruikers-ID</label>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" value="<?php echo $user['User_ID']; ?>" disabled>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <label for="voornaam" class="col-md-3 col-form-label">Voornaam *</label>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" id="voornaam" name="voornaam" value="<?php echo htmlspecialchars($user['Voornaam']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <label for="naam" class="col-md-3 col-form-label">Achternaam *</label>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" id="naam" name="naam" value="<?php echo htmlspecialchars($user['Naam']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <label for="email" class="col-md-3 col-form-label">E-mailadres *</label>
                                <div class="col-md-9">
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['Emailadres']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <label for="telefoon" class="col-md-3 col-form-label">Telefoonnummer</label>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" id="telefoon" name="telefoon" value="<?php echo htmlspecialchars($user['Telefoon'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <label for="type" class="col-md-3 col-form-label">Gebruikerstype *</label>
                                <div class="col-md-9">
                                    <select class="form-select" id="type" name="type" onchange="toggleTypeFields()">
                                        <option value="student" <?php echo ($user['Type'] == 'student') ? 'selected' : ''; ?>>Student</option>
                                        <option value="docent" <?php echo ($user['Type'] == 'docent') ? 'selected' : ''; ?>>Docent</option>
                                        <option value="onderzoeker" <?php echo ($user['Type'] == 'onderzoeker') ? 'selected' : ''; ?>>Onderzoeker</option>
                                        <option value="beheerder" <?php echo ($user['Type'] == 'beheerder') ? 'selected' : ''; ?>>Beheerder</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3" id="vivesIdContainer" style="<?php echo ($user['Type'] == 'student' || $user['Type'] == 'docent') ? '' : 'display: none;'; ?>">
                                <label for="vives_id" class="col-md-3 col-form-label">VIVES ID *</label>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" id="vives_id" name="vives_id" placeholder="Bijv. R1234567 of U1234567" 
                                           value="<?php echo isset($vivesInfo['Vives_ID']) ? htmlspecialchars($vivesInfo['Vives_ID']) : ''; ?>">
                                    <div class="form-text">R-nummer voor studenten, U-nummer voor docenten</div>
                                </div>
                            </div>
                            
                            <div class="row mb-3" id="opleidingContainer" style="<?php echo ($user['Type'] == 'student') ? '' : 'display: none;'; ?>">
                                <label for="opleiding_id" class="col-md-3 col-form-label">Opleiding *</label>
                                <div class="col-md-9">
                                    <select class="form-select" id="opleiding_id" name="opleiding_id">
                                        <option value="">Selecteer opleiding</option>
                                        <?php foreach ($opleidingen as $opleiding): ?>
                                            <option value="<?php echo $opleiding['id']; ?>" 
                                                <?php echo (isset($vivesInfo['opleiding_id']) && $vivesInfo['opleiding_id'] == $opleiding['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($opleiding['naam']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <label class="col-md-3 col-form-label">Hulp nodig</label>
                                <div class="col-md-9">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="hulp_nodig" id="hulp_ja" value="1" 
                                            <?php echo (isset($user['HulpNodig']) && $user['HulpNodig'] == 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="hulp_ja">Ja</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="hulp_nodig" id="hulp_nee" value="0"
                                            <?php echo (isset($user['HulpNodig']) && $user['HulpNodig'] == 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="hulp_nee">Nee</label>
                                    </div>
                                    <div class="form-text">Bepaalt of de gebruiker extra hulp nodig heeft bij het gebruik van het systeem.</div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h5 class="mb-3">Wachtwoord wijzigen (optioneel)</h5>
                            
                            <div class="row mb-3">
                                <label for="new_password" class="col-md-3 col-form-label">Nieuw wachtwoord</label>
                                <div class="col-md-9">
                                    <input type="password" class="form-control" id="new_password" name="new_password" minlength="6">
                                    <div class="form-text">Laat leeg om het huidige wachtwoord te behouden. Minimaal 6 tekens.</div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <label for="confirm_password" class="col-md-3 col-form-label">Bevestig wachtwoord</label>
                                <div class="col-md-9">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="users.php" class="btn btn-secondary me-md-2">Annuleren</a>
                                <button type="submit" class="btn btn-primary">Wijzigingen opslaan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleTypeFields() {
            const type = document.getElementById('type').value;
            const vivesIdContainer = document.getElementById('vivesIdContainer');
            const opleidingContainer = document.getElementById('opleidingContainer');
            
            // Reset displays
            vivesIdContainer.style.display = 'none';
            opleidingContainer.style.display = 'none';
            
            // Show appropriate fields based on user type
            if (type === 'student' || type === 'docent') {
                vivesIdContainer.style.display = '';
                
                if (type === 'student') {
                    opleidingContainer.style.display = '';
                }
            }
        }
        
        // Run on page load
        document.addEventListener('DOMContentLoaded', toggleTypeFields);
    </script>
</body>
</html>