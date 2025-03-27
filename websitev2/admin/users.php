<?php
session_start();
require_once '../config.php';

// Ensure user is admin
requireAdmin();

$currentPage = 'admin';
$pageTitle = 'Gebruikers Beheren - Admin';

// Process deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $userId = $_GET['delete'];
    
    // Don't allow deleting yourself
    if ($userId == $_SESSION['user_id']) {
        setFlashMessage('Je kunt je eigen account niet verwijderen.', 'danger');
    } else {
        // Check if user has any reservations
        $stmt = $conn->prepare("SELECT COUNT(*) FROM reservations WHERE user_id = ?");
        $stmt->execute([$userId]);
        $hasReservations = $stmt->fetchColumn() > 0;
        
        if ($hasReservations) {
            setFlashMessage('Kan gebruiker niet verwijderen: er zijn actieve reserveringen voor deze gebruiker.', 'danger');
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $result = $stmt->execute([$userId]);
            
            if ($result) {
                setFlashMessage('Gebruiker succesvol verwijderd.', 'success');
            } else {
                setFlashMessage('Fout bij het verwijderen van de gebruiker.', 'danger');
            }
        }
    }
    redirect('admin/users.php');
}

// Get all users
$stmt = $conn->query("SELECT * FROM users ORDER BY username");
$users = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Gebruikers Beheren</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="dashboard.php">Admin Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Gebruikers</li>
            </ol>
        </nav>
    </div>
    
    <div class="mb-4">
        <a href="add-user.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Nieuwe Gebruiker Toevoegen
        </a>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Gebruikersnaam</th>
                            <th>E-mail</th>
                            <th>Naam</th>
                            <th>Aangemaakt op</th>
                            <th>Admin</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php 
                                            $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                                            echo !empty($fullName) ? htmlspecialchars($fullName) : '-';
                                        ?>
                                    </td>
                                    <td><?php echo date('d-m-Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <?php if ($user['is_admin']): ?>
                                            <span class="badge bg-primary">Ja</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Nee</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary me-1">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <a href="#" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $user['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            
                                            <!-- Delete Modal -->
                                            <div class="modal fade" id="deleteModal<?php echo $user['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $user['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="deleteModalLabel<?php echo $user['id']; ?>">Bevestig Verwijdering</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            Weet je zeker dat je de gebruiker "<?php echo htmlspecialchars($user['username']); ?>" wilt verwijderen?
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                                                            <a href="users.php?delete=<?php echo $user['id']; ?>" class="btn btn-danger">Verwijderen</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">Geen gebruikers gevonden.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>