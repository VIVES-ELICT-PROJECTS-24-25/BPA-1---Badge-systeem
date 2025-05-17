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
    'Type' => 'student',
    'HulpNodig' => 0,
    'HuidigActief' => 1,
    
    // VIVES gerelateerde velden
    'Vives_ID' => '',
    'rfidkaartnr' => '',
    'opleiding_id' => '',
    'Vives_Type' => 'student'
];

// Haal alle opleidingen op
try {
    $stmt = $conn->prepare("SELECT id, naam FROM opleidingen ORDER BY naam");
    $stmt->execute();
    $opleidingen = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = 'Fout bij ophalen van opleidingen: ' . $e->getMessage();
}

// Formulier verwerken
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Formuliergegevens ophalen
    $formData = [
        'Voornaam' => trim($_POST['Voornaam'] ?? ''),
        'Naam' => trim($_POST['Naam'] ?? ''),
        'Emailadres' => trim($_POST['Emailadres'] ?? ''),
        'Telefoon' => trim($_POST['Telefoon'] ?? ''),
        'Type' => trim($_POST['Type'] ?? 'student'),
        'HulpNodig' => isset($_POST['HulpNodig']) ? 1 : 0,
        'HuidigActief' => isset($_POST['HuidigActief']) ? 1 : 0,
        
        // VIVES gerelateerde velden
        'Vives_ID' => trim($_POST['Vives_ID'] ?? ''),
        'rfidkaartnr' => trim($_POST['rfidkaartnr'] ?? ''),
        'opleiding_id' => $_POST['opleiding_id'] ?? null,
        'Vives_Type' => trim($_POST['Vives_Type'] ?? '')
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
    
    // Specifieke validatie voor studentengegevens
    if ($formData['Type'] == 'student' && empty($formData['opleiding_id'])) {
        $validationErrors[] = 'Een opleiding is verplicht voor studenten';
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
                // Start transactie
                $conn->beginTransaction();
                
                // Vast tijdelijk wachtwoord
                $tempPassword = 'vives2025';
                $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
                
                // Genereer een nieuw User_ID (auto increment simuleren) - DEZELFDE METHODE ALS IN REGISTER.PHP
                $stmtMaxId = $conn->query("SELECT MAX(User_ID) as maxId FROM User");
                $result = $stmtMaxId->fetch();
                $newUserId = ($result['maxId'] ?? 0) + 1;

                // Gebruiker toevoegen MET EXPLICIET USER_ID
                $stmt = $conn->prepare("
                    INSERT INTO User 
                    (User_ID, Voornaam, Naam, Emailadres, Telefoon, Type, Wachtwoord, AanmaakAccount, HuidigActief, HulpNodig) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
                ");
                
                $result = $stmt->execute([
                    $newUserId,
                    $formData['Voornaam'], 
                    $formData['Naam'], 
                    $formData['Emailadres'], 
                    $formData['Telefoon'], 
                    $formData['Type'],
                    $hashedPassword,
                    $formData['HuidigActief'],
                    $formData['HulpNodig']
                ]);
                
                if (!$result) {
                    throw new PDOException("Insert in User tabel mislukt");
                }
                
                // Als het een student, docent of onderzoeker is, voeg VIVES gegevens toe
                if ($formData['Type'] == 'student' || $formData['Type'] == 'docent' || $formData['Type'] == 'onderzoeker') {
                    // Bepaal VIVES type als het niet expliciet is ingesteld
                    if (empty($formData['Vives_Type'])) {
                        if ($formData['Type'] == 'student') $formData['Vives_Type'] = 'student';
                        elseif ($formData['Type'] == 'docent') $formData['Vives_Type'] = 'medewerker';
                        elseif ($formData['Type'] == 'onderzoeker') $formData['Vives_Type'] = 'onderzoeker';
                    }
                    
                    // Voeg VIVES record toe
                    $stmt = $conn->prepare("
                        INSERT INTO Vives 
                        (User_ID, Voornaam, Vives_id, rfidkaartnr, opleiding_id, Type) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    
                    $vives_result = $stmt->execute([
                        $newUserId,
                        $formData['Voornaam'],
                        $formData['Vives_ID'],
                        $formData['rfidkaartnr'],
                        $formData['Type'] == 'student' ? $formData['opleiding_id'] : null,
                        $formData['Vives_Type']
                    ]);
                    
                    if (!$vives_result) {
                        throw new PDOException("Insert in Vives tabel mislukt");
                    }
                }
                
                // Commit transactie
                $conn->commit();

                // Redirect naar users.php met succesmelding
                $_SESSION['success_message'] = 'Gebruiker succesvol aangemaakt met tijdelijk wachtwoord "vives2025".';
                header('Location: users.php');
                exit();
            }
        } catch (PDOException $e) {
            // Rollback bij fouten
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
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
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="users.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Terug naar gebruikersoverzicht
                        </a>
                    </div>
                </div>
                
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Fout:</strong> <?php echo $errorMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-user-plus me-1"></i>
                        Nieuwe Gebruiker
                    </div>
                    <div class="card-body">
                        <form method="POST" action="add_user.php">
                            <!-- Tabbladen voor verschillende secties -->
                            <ul class="nav nav-tabs mb-4" id="userTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab" aria-controls="basic" aria-selected="true">
                                        <i class="fas fa-user me-2"></i>Basisgegevens
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="vives-tab" data-bs-toggle="tab" data-bs-target="#vives" type="button" role="tab" aria-controls="vives" aria-selected="false">
                                        <i class="fas fa-university me-2"></i>VIVES Informatie
                                    </button>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="userTabContent">
                                <!-- Basisgegevens Tab -->
                                <div class="tab-pane fade show active" id="basic" role="tabpanel" aria-labelledby="basic-tab">
                                    <div class="row mb-3">
                                        <label for="Voornaam" class="col-md-3 col-form-label">Voornaam *</label>
                                        <div class="col-md-9">
                                            <input type="text" class="form-control" id="Voornaam" name="Voornaam" 
                                                   value="<?php echo htmlspecialchars($formData['Voornaam']); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <label for="Naam" class="col-md-3 col-form-label">Achternaam *</label>
                                        <div class="col-md-9">
                                            <input type="text" class="form-control" id="Naam" name="Naam" 
                                                   value="<?php echo htmlspecialchars($formData['Naam']); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <label for="Emailadres" class="col-md-3 col-form-label">E-mailadres *</label>
                                        <div class="col-md-9">
                                            <input type="email" class="form-control" id="Emailadres" name="Emailadres" 
                                                   value="<?php echo htmlspecialchars($formData['Emailadres']); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <label for="Telefoon" class="col-md-3 col-form-label">Telefoonnummer</label>
                                        <div class="col-md-9">
                                            <input type="tel" class="form-control" id="Telefoon" name="Telefoon" 
                                                   value="<?php echo htmlspecialchars($formData['Telefoon']); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <label for="Type" class="col-md-3 col-form-label">Gebruikerstype *</label>
                                        <div class="col-md-9">
                                            <select class="form-select" id="Type" name="Type" onchange="toggleTypeFields()">
                                                <option value="student" <?php echo ($formData['Type'] == 'student') ? 'selected' : ''; ?>>Student</option>
                                                <option value="docent" <?php echo ($formData['Type'] == 'docent') ? 'selected' : ''; ?>>Docent</option>
                                                <option value="onderzoeker" <?php echo ($formData['Type'] == 'onderzoeker') ? 'selected' : ''; ?>>Onderzoeker</option>
                                                <option value="beheerder" <?php echo ($formData['Type'] == 'beheerder') ? 'selected' : ''; ?>>Beheerder</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <label class="col-md-3 col-form-label">Hulp nodig</label>
                                        <div class="col-md-9">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="HulpNodig" id="hulp_ja" value="1" 
                                                    <?php echo ($formData['HulpNodig'] == 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="hulp_ja">Ja</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="HulpNodig" id="hulp_nee" value="0"
                                                    <?php echo ($formData['HulpNodig'] == 0) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="hulp_nee">Nee</label>
                                            </div>
                                            <div class="form-text">Bepaalt of de gebruiker extra hulp nodig heeft bij het gebruik van het systeem.</div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <label class="col-md-3 col-form-label">Status</label>
                                        <div class="col-md-9">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="HuidigActief" id="status_actief" value="1" 
                                                    <?php echo ($formData['HuidigActief'] == 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="status_actief">Actief</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="HuidigActief" id="status_inactief" value="0"
                                                    <?php echo ($formData['HuidigActief'] == 0) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="status_inactief">Inactief</label>
                                            </div>
                                            <div class="form-text">Bepaalt of het account actief is of niet.</div>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Het tijdelijke wachtwoord voor nieuwe gebruikers is <strong>"vives2025"</strong>. 
                                        Gebruikers kunnen dit later zelf wijzigen na het inloggen.
                                    </div>
                                </div>
                                
                                <!-- VIVES Informatie Tab -->
                                <div class="tab-pane fade" id="vives" role="tabpanel" aria-labelledby="vives-tab">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> 
                                        De VIVES informatie is alleen relevant voor gebruikers met type 'Student', 'Docent' of 'Onderzoeker'.
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <label for="Vives_ID" class="col-md-3 col-form-label">VIVES ID</label>
                                        <div class="col-md-9">
                                            <input type="text" class="form-control" id="Vives_ID" name="Vives_ID" placeholder="Bijv. R1234567 of U1234567" 
                                                   value="<?php echo htmlspecialchars($formData['Vives_ID']); ?>">
                                            <div class="form-text">R-nummer voor studenten, U-nummer voor docenten/onderzoekers</div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <label for="rfidkaartnr" class="col-md-3 col-form-label">RFID Kaartnummer</label>
                                        <div class="col-md-9">
                                            <input type="text" class="form-control" id="rfidkaartnr" name="rfidkaartnr" 
                                                   value="<?php echo htmlspecialchars($formData['rfidkaartnr']); ?>">
                                            <div class="form-text">Het RFID kaartnummer van de gebruiker voor toegang</div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3" id="opleidingContainer">
                                        <label for="opleiding_id" class="col-md-3 col-form-label">Opleiding</label>
                                        <div class="col-md-9">
                                            <select class="form-select" id="opleiding_id" name="opleiding_id">
                                                <option value="">Selecteer opleiding</option>
                                                <?php foreach ($opleidingen as $opleiding): ?>
                                                    <option value="<?php echo $opleiding['id']; ?>" 
                                                        <?php echo ($formData['opleiding_id'] == $opleiding['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($opleiding['naam']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text">Verplicht voor studenten</div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <label for="Vives_Type" class="col-md-3 col-form-label">VIVES Type</label>
                                        <div class="col-md-9">
                                            <select class="form-select" id="Vives_Type" name="Vives_Type">
                                                <option value="student" <?php echo ($formData['Vives_Type'] == 'student') ? 'selected' : ''; ?>>Student</option>
                                                <option value="medewerker" <?php echo ($formData['Vives_Type'] == 'medewerker') ? 'selected' : ''; ?>>Medewerker</option>
                                                <option value="onderzoeker" <?php echo ($formData['Vives_Type'] == 'onderzoeker') ? 'selected' : ''; ?>>Onderzoeker</option>
                                                <option value="ander" <?php echo ($formData['Vives_Type'] == 'ander') ? 'selected' : ''; ?>>Ander</option>
                                            </select>
                                            <div class="form-text">Het type gebruiker binnen VIVES</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="users.php" class="btn btn-secondary me-md-2">Annuleren</a>
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
    
    <script>
        function toggleTypeFields() {
            const type = document.getElementById('Type').value;
            const vivesTab = document.getElementById('vives-tab');
            const opleidingContainer = document.getElementById('opleidingContainer');
            
            // Activeer/deactiveer tabblad op basis van type
            if (type === 'student' || type === 'docent' || type === 'onderzoeker') {
                vivesTab.classList.remove('disabled');
                
                // Automatisch het VIVES type selecteren op basis van het gebruikerstype
                const vivesTypeSelect = document.getElementById('Vives_Type');
                if (type === 'student') {
                    vivesTypeSelect.value = 'student';
                    opleidingContainer.style.display = '';
                } else if (type === 'docent') {
                    vivesTypeSelect.value = 'medewerker';
                    opleidingContainer.style.display = 'none';
                } else if (type === 'onderzoeker') {
                    vivesTypeSelect.value = 'onderzoeker';
                    opleidingContainer.style.display = 'none';
                }
            } else {
                vivesTab.classList.add('disabled');
            }
        }
        
        // Run on page load
        document.addEventListener('DOMContentLoaded', toggleTypeFields);
    </script>
</body>
</html>