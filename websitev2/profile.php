<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

$currentPage = 'profile';
$pageTitle = 'Mijn Profiel - 3D Printer Reserveringssysteem';

// Controleer of gebruiker is ingelogd
if (!isset($_SESSION['User_ID'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = $_SESSION['User_ID'];
$error = '';
$success = '';

// Gebruikersgegevens ophalen
$stmt = $conn->prepare("SELECT * FROM User WHERE User_ID = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: logout.php');
    exit;
}

// Gebruikersgegevens bijwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $voornaam = trim($_POST['voornaam'] ?? '');
    $naam = trim($_POST['naam'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefoon = trim($_POST['telefoon'] ?? '');
    
    // Validatie
    if (empty($voornaam) || empty($naam) || empty($email)) {
        $error = 'Voornaam, achternaam en e-mailadres zijn verplicht.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ongeldig e-mailadres.';
    } else {
        // Controleer of e-mail al in gebruik is door een andere gebruiker
        $stmt = $conn->prepare("SELECT User_ID FROM User WHERE Emailadres = ? AND User_ID != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->rowCount() > 0) {
            $error = 'Dit e-mailadres is al in gebruik door een ander account.';
        } else {
            try {
                $stmt = $conn->prepare("
                    UPDATE User 
                    SET Voornaam = ?, Naam = ?, Emailadres = ?, Telefoon = ? 
                    WHERE User_ID = ?
                ");
                $stmt->execute([$voornaam, $naam, $email, $telefoon, $userId]);
                
                // Update session variables
                $_SESSION['Voornaam'] = $voornaam;
                $_SESSION['Naam'] = $naam;
                
                $success = 'Je profielgegevens zijn bijgewerkt.';
                
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM User WHERE User_ID = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
            } catch (PDOException $e) {
                $error = 'Er is een fout opgetreden bij het bijwerken van je profiel: ' . $e->getMessage();
            }
        }
    }
}

// Wachtwoord wijzigen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Alle wachtwoordvelden zijn verplicht.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Nieuwe wachtwoorden komen niet overeen.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Nieuw wachtwoord moet minimaal 6 tekens bevatten.';
    } elseif (!password_verify($currentPassword, $user['Wachtwoord'])) {
        $error = 'Huidig wachtwoord is onjuist.';
    } else {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE User SET Wachtwoord = ? WHERE User_ID = ?");
            $stmt->execute([$hashedPassword, $userId]);
            
            $success = 'Je wachtwoord is succesvol gewijzigd.';
        } catch (PDOException $e) {
            $error = 'Er is een fout opgetreden bij het wijzigen van je wachtwoord: ' . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-lg-3">
            <!-- Profiel sidebar -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['Voornaam'] . '+' . $user['Naam']); ?>&background=random&size=128" class="rounded-circle img-fluid mb-3" alt="Profile Image">
                    <h5 class="mb-1"><?php echo htmlspecialchars($user['Voornaam'] . ' ' . $user['Naam']); ?></h5>
                    <p class="text-muted mb-3"><?php echo ucfirst(htmlspecialchars($user['Type'])); ?></p>
                    <p class="mb-2"><i class="fas fa-calendar-check me-2"></i> Lid sinds <?php echo date('d-m-Y', strtotime($user['AanmaakAccount'])); ?></p>
                </div>
            </div>
            
            <!-- Navigatie links -->
            <div class="card mb-4">
                <div class="list-group list-group-flush">
                    <a href="#profile-info" class="list-group-item list-group-item-action active">
                        <i class="fas fa-user me-2"></i> Profielgegevens
                    </a>
                    <a href="#password" class="list-group-item list-group-item-action">
                        <i class="fas fa-key me-2"></i> Wachtwoord wijzigen
                    </a>
                    <a href="reservations.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar-alt me-2"></i> Mijn Reserveringen
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-lg-9">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Profielgegevens -->
            <div class="card mb-4" id="profile-info">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i> Profielgegevens</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="profile.php">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="voornaam" class="form-label">Voornaam</label>
                                <input type="text" class="form-control" id="voornaam" name="voornaam" value="<?php echo htmlspecialchars($user['Voornaam']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="naam" class="form-label">Achternaam</label>
                                <input type="text" class="form-control" id="naam" name="naam" value="<?php echo htmlspecialchars($user['Naam']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">E-mailadres</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['Emailadres']); ?>" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label for="telefoon" class="form-label">Telefoonnummer</label>
                            <input type="text" class="form-control" id="telefoon" name="telefoon" value="<?php echo htmlspecialchars($user['Telefoon']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="type" class="form-label">Type gebruiker</label>
                            <input type="text" class="form-control" id="type" value="<?php echo ucfirst(htmlspecialchars($user['Type'])); ?>" disabled>
                            <div class="form-text">Je accounttype kan alleen worden gewijzigd door een beheerder.</div>
                        </div>
                        
                        <input type="hidden" name="update_profile" value="1">
                        <button type="submit" class="btn btn-primary">Opslaan</button>
                    </form>
                </div>
            </div>
            
            <!-- Wachtwoord Wijzigen -->
            <div class="card mb-4" id="password">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-key me-2"></i> Wachtwoord wijzigen</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="profile.php#password">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Huidig wachtwoord</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nieuw wachtwoord</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                            <div class="form-text">Minimaal 6 tekens</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Bevestig nieuw wachtwoord</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required>
                        </div>
                        
                        <input type="hidden" name="change_password" value="1">
                        <button type="submit" class="btn btn-primary">Wachtwoord wijzigen</button>
                    </form>
                </div>
            </div>
            
            <!-- Gevaren zone -->
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Gevaren zone</h5>
                </div>
                <div class="card-body">
                    <h6>Account verwijderen</h6>
                    <p>Wil je je account volledig verwijderen? Deze actie kan niet ongedaan worden gemaakt.</p>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                        Account verwijderen
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Account verwijderen modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteAccountModalLabel">Account verwijderen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Weet je zeker dat je je account wilt verwijderen? Deze actie is permanent en kan niet ongedaan worden gemaakt.</p>
                <p>Alle gegevens gerelateerd aan dit account zullen worden verwijderd, inclusief:</p>
                <ul>
                    <li>Je persoonlijke gegevens</li>
                    <li>Reserveringsgeschiedenis</li>
                    <li>Alle actieve reserveringen</li>
                </ul>
                
                <div class="alert alert-warning mt-3">
                    <strong>Let op:</strong> Active reserveringen worden geannuleerd.
                </div>
                
                <form id="deleteAccountForm" method="post" action="delete-account.php">
                    <div class="mb-3">
                        <label for="delete_confirm" class="form-label">Typ "VERWIJDER" om te bevestigen</label>
                        <input type="text" class="form-control" id="delete_confirm" required pattern="VERWIJDER">
                    </div>
                    
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Voer je wachtwoord in voor bevestiging</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                <button type="submit" form="deleteAccountForm" class="btn btn-danger">Permanent verwijderen</button>
            </div>
        </div>
    </div>
</div>

<script>
// Script voor form validatie en toggle sectie
document.addEventListener('DOMContentLoaded', function() {
    // Anker links
    document.querySelectorAll('.list-group-item').forEach(function(link) {
        link.addEventListener('click', function(e) {
            // Verwijder active class van alle links
            document.querySelectorAll('.list-group-item').forEach(function(item) {
                item.classList.remove('active');
            });
            
            // Voeg active class toe aan geklikte link
            this.classList.add('active');
        });
    });
    
    // Validatie voor nieuw wachtwoord
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    confirmPassword.addEventListener('input', function() {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Wachtwoorden komen niet overeen');
        } else {
            confirmPassword.setCustomValidity('');
        }
    });
    
    // Account verwijderen validatie
    const deleteConfirm = document.getElementById('delete_confirm');
    const deleteForm = document.getElementById('deleteAccountForm');
    
    deleteForm.addEventListener('submit', function(e) {
        if (deleteConfirm.value !== 'VERWIJDER') {
            e.preventDefault();
            alert('Je moet "VERWIJDER" typen (in hoofdletters) om door te gaan.');
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>