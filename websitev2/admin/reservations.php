<?php
session_start();
require_once '../config.php';

// Ensure user is admin
requireAdmin();

$currentPage = 'admin';
$pageTitle = 'Reserveringen Beheren - Admin';

// Process deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $reservationId = $_GET['delete'];
    
    $stmt = $conn->prepare("DELETE FROM reservations WHERE id = ?");
    $result = $stmt->execute([$reservationId]);
    
    if ($result) {
        setFlashMessage('Reservering succesvol verwijderd.', 'success');
    } else {
        setFlashMessage('Fout bij het verwijderen van de reservering.', 'danger');
    }
    redirect('admin/reservations.php');
}

// Filter options
$filterUser = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$filterPrinter = isset($_GET['printer_id']) ? intval($_GET['printer_id']) : null;
$filterStatus = isset($_GET['status']) ? sanitizeInput($_GET['status']) : null;
$filterDate = isset($_GET['date']) ? sanitizeInput($_GET['date']) : null;

// Build query
$query = "
    SELECT r.*, u.username, p.name AS printer_name 
    FROM reservations r
    JOIN users u ON r.user_id = u.id
    JOIN printers p ON r.printer_id = p.id
    WHERE 1=1
";
$params = [];

if ($filterUser) {
    $query .= " AND r.user_id = ?";
    $params[] = $filterUser;
}

if ($filterPrinter) {
    $query .= " AND r.printer_id = ?";
    $params[] = $filterPrinter;
}

if ($filterStatus) {
    $query .= " AND r.status = ?";
    $params[] = $filterStatus;
}

if ($filterDate) {
    $query .= " AND DATE(r.start_time) = ?";
    $params[] = $filterDate;
}

$query .= " ORDER BY r.start_time DESC";

// Get reservations
$stmt = $conn->prepare($query);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

// Get all users and printers for filters
$users = $conn->query("SELECT id, username FROM users ORDER BY username")->fetchAll();
$printers = $conn->query("SELECT id, name FROM printers ORDER BY name")->fetchAll();

include '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Reserveringen Beheren</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="dashboard.php">Admin Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Reserveringen</li>
            </ol>
        </nav>
    </div>
    
    <div class="mb-4">
        <a href="add-reservation.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Nieuwe Reservering Toevoegen
        </a>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Filters</h5>
            <form action="" method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="user_id" class="form-label">Gebruiker</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="">Alle Gebruikers</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $filterUser == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="printer_id" class="form-label">Printer</label>
                    <select class="form-select" id="printer_id" name="printer_id">
                        <option value="">Alle Printers</option>
                        <?php foreach ($printers as $printer): ?>
                            <option value="<?php echo $printer['id']; ?>" <?php echo $filterPrinter == $printer['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($printer['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Alle Statussen</option>
                        <option value="confirmed" <?php echo $filterStatus == 'confirmed' ? 'selected' : ''; ?>>Bevestigd</option>
                        <option value="pending" <?php echo $filterStatus == 'pending' ? 'selected' : ''; ?>>In Afwachting</option>
                        <option value="cancelled" <?php echo $filterStatus == 'cancelled' ? 'selected' : ''; ?>>Geannuleerd</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date" class="form-label">Datum</label>
                    <input type="date" class="form-control" id="date" name="date" value="<?php echo $filterDate; ?>">
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="reservations.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Gebruiker</th>
                            <th>Printer</th>
                            <th>Start Tijd</th>
                            <th>Eind Tijd</th>
                            <th>Kleur</th>
                            <th>Status</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($reservations) > 0): ?>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td><?php echo $reservation['id']; ?></td>
                                    <td><?php echo htmlspecialchars($reservation['username']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['printer_name']); ?></td>
                                    <td><?php echo date('d-m-Y H:i', strtotime($reservation['start_time'])); ?></td>
                                    <td><?php echo date('d-m-Y H:i', strtotime($reservation['end_time'])); ?></td>
                                    <td>
                                        <?php if ($reservation['color_printing']): ?>
                                            <span class="badge bg-success">Ja</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Nee</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            if ($reservation['status'] === 'confirmed') echo 'success';
                                            elseif ($reservation['status'] === 'pending') echo 'warning';
                                            else echo 'danger';
                                        ?>">
                                            <?php 
                                                if ($reservation['status'] === 'confirmed') echo 'Bevestigd';
                                                elseif ($reservation['status'] === 'pending') echo 'In Afwachting';
                                                else echo 'Geannuleerd';
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="edit-reservation.php?id=<?php echo $reservation['id']; ?>" class="btn btn-sm btn-primary me-1">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $reservation['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        
                                        <!-- Delete Modal -->
                                        <div class="modal fade" id="deleteModal<?php echo $reservation['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $reservation['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="deleteModalLabel<?php echo $reservation['id']; ?>">Bevestig Verwijdering</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Weet je zeker dat je deze reservering wilt verwijderen?
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                                                        <a href="reservations.php?delete=<?php echo $reservation['id']; ?>" class="btn btn-danger">Verwijderen</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">Geen reserveringen gevonden.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>