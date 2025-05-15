<?php
// Admin toegang controle
require_once 'admin.php';

$pageTitle = 'Gebruiker Toevoegen - 3D Printer Reserveringssysteem';
$currentPage = 'admin-users';

// Initialiseer variabelen
$errorMessage = '';
$successMessage = '';

// Standaard formulier waarden
$formData = [
    'Voornaam' => '',
    'Naam' => '',
    'Emailadres' => '',
    'Telefoon' => '',
    'Type' => 'student'
];

// Formulier verwerken
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Formuliergegevens ophalen
    $formData = [
        'Voornaam' => trim($_POST['Voornaam']),
        'Naam' => trim($_POST['Naam']),
        'Emailadres' => trim($_POST['Emailadres']),
        'Telefoon' => trim($_POST['Telefoon']),
        'Type' => trim($_POST['Type'])
    ];

    // Validatie
    $validationErrors = [];
    if (empty($formData['Voornaam'])) {
        $validationErrors[] = 'Voornaam is verplicht';
    }
    if (empty($formData['Naam'])) {
        $validationErrors[] = 'Achternaam is verplicht';
    }
    if (empty($formData['Emailadres']) || !filter_var($formData['Emailadres'], FILTER_VALIDATE_EMAIL)) {
        $validationErrors[] = 'Ongeldig of leeg e-mailadres';
    }

    // Als er geen validatiefouten zijn
    if (empty($validationErrors)) {
        try {
            // Controleer of het e-mailadres al bestaat
            $stmt = $conn->prepare("SELECT COUNT(*) FROM User WHERE Emailadres = ?");
            $stmt->execute([$formData['Emailadres']]);
            if ($stmt->fetchColumn() > 0) {
                $errorMessage = 'Dit e-mailadres is al geregistreerd.';
            } else {
                // Vast tijdelijk wachtwoord
                $tempPassword = 'vives2025';
                $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

                // Gebruiker toevoegen
                $stmt = $conn->prepare("
                    INSERT INTO User 
                    (Voornaam, Naam, Emailadres, Telefoon, Type, Wachtwoord, AanmaakAccount, HuidigActief) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)
                ");
                $stmt->execute([
                    $formData['Voornaam'], 
                    $formData['Naam'], 
                    $formData['Emailadres'], 
                    $formData['Telefoon'], 
                    $formData['Type'],
                    $hashedPassword
                ]);

                // Redirect naar users.php met succesmelding
                $_SESSION['success_message'] = 'Gebruiker succesvol aangemaakt met tijdelijk wachtwoord "vives2025".';
                header('Location: users.php');
                exit();
            }
        } catch (PDOException $e) {
            $errorMessage = 'Fout bij toevoegen gebruiker: ' . $e->getMessage();
        }
    } else {
        $errorMessage = implode('<br>', $validationErrors);
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
                    <h1 class="h2">Gebruiker Toevoegen</h1>
                </div>
                
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $errorMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user-plus me-1"></i>
                        Nieuwe Gebruiker
                    </div>
                    <div class="card-body">
                        <form method="POST" action="add_user.php">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="Voornaam" class="form-label">Voornaam</label>
                                    <input type="text" class="form-control" id="Voornaam" name="Voornaam" 
                                           value="<?php echo htmlspecialchars($formData['Voornaam']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="Naam" class="form-label">Achternaam</label>
                                    <input type="text" class="form-control" id="Naam" name="Naam" 
                                           value="<?php echo htmlspecialchars($formData['Naam']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="Emailadres" class="form-label">E-mailadres</label>
                                    <input type="email" class="form-control" id="Emailadres" name="Emailadres" 
                                           value="<?php echo htmlspecialchars($formData['Emailadres']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="Telefoon" class="form-label">Telefoon</label>
                                    <input type="tel" class="form-control" id="Telefoon" name="Telefoon" 
                                           value="<?php echo htmlspecialchars($formData['Telefoon']); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="Type" class="form-label">Gebruikerstype</label>
                                <select class="form-select" id="Type" name="Type">
                                    <option value="student" <?php echo ($formData['Type'] == 'student') ? 'selected' : ''; ?>>Student</option>
                                    <option value="onderzoeker" <?php echo ($formData['Type'] == 'onderzoeker') ? 'selected' : ''; ?>>Onderzoeker</option>
                                    <option value="beheerder" <?php echo ($formData['Type'] == 'beheerder') ? 'selected' : ''; ?>>Beheerder</option>
                                </select>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="users.php" class="btn btn-secondary">Annuleren</a>
                                <button type="submit" class="btn btn-primary">Gebruiker Opslaan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>