<?php
session_start();

// Controleer of de gebruiker ingelogd is
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Controleer of de gebruiker een admin is en stuur door naar admin pagina
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: adminv1.php");
    exit;
}

require_once 'config.php';
$conn = getConnection();

// Haal gebruikersgegevens op
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM Gebruiker WHERE User_ID = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Haal gebruikersreserveringen op
$reservations = [];
$stmt = $conn->prepare("SELECT r.*, p.Naam as PrinterNaam FROM Reservatie r 
                        JOIN Printer p ON r.Printer_ID = p.Printer_ID 
                        WHERE r.User_ID = ? 
                        ORDER BY r.Date_Time_res DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $reservations[] = $row;
}
$stmt->close();
$conn->close();

// Functie om datum/tijd te formatteren
function formatDateTime($dateTime) {
    if (!$dateTime) return '-';
    $date = new DateTime($dateTime);
    return $date->format('d-m-Y H:i');
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gebruiker Dashboard - MaakLab Badge Systeem</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .user-dashboard {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        .user-welcome h1 {
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .user-welcome p {
            color: #7f8c8d;
        }
        .user-info-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 30px;
        }
        .user-info-card h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 10px;
        }
        .info-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            padding: 10px 0;
        }
        .info-label {
            font-weight: 600;
            color: #7f8c8d;
        }
        .reservation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .empty-state {
            text-align: center;
            padding: 40px 0;
            color: #7f8c8d;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #bdc3c7;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
        }
        .status-active {
            background-color: rgba(46, 204, 113, 0.2);
            color: #27ae60;
        }
        .status-upcoming {
            background-color: rgba(52, 152, 219, 0.2);
            color: #2980b9;
        }
        .status-completed {
            background-color: rgba(127, 140, 141, 0.2);
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <h1><i class="fas fa-print"></i> MaakLab Badge Systeem</h1>
        </div>
        <div class="user-info">
            <span>Welkom, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Uitloggen</a>
        </div>
    </header>

    <div class="user-dashboard">
        <div class="user-header">
            <div class="user-welcome">
                <h1>Welkom, <?php echo htmlspecialchars($user['Voornaam']); ?>!</h1>
                <p>Hier zie je je persoonlijke gegevens en reserveringen.</p>
            </div>
            <div class="current-time">
                <i class="fas fa-clock"></i> <?php echo date('d-m-Y H:i'); ?>
            </div>
        </div>
        
        <div class="user-info-card">
            <h2>Persoonlijke Gegevens</h2>
            <div class="info-row">
                <div class="info-label">Naam:</div>
                <div><?php echo htmlspecialchars($user['Voornaam'] . ' ' . $user['Naam']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">E-mailadres:</div>
                <div><?php echo htmlspecialchars($user['Email']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Telefoonnummer:</div>
                <div><?php echo $user['Telnr'] ? htmlspecialchars($user['Telnr']) : '-'; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Rol:</div>
                <div><?php echo htmlspecialchars($user['rol']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Account aangemaakt:</div>
                <div><?php echo htmlspecialchars(formatDateTime($user['Aanmaak_Acc'])); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Laatste login:</div>
                <div><?php echo $user['Laatste_Aanmeld'] ? htmlspecialchars(formatDateTime($user['Laatste_Aanmeld'])) : '-'; ?></div>
            </div>
        </div>
        
        <div class="user-info-card">
            <div class="reservation-header">
                <h2>Mijn Reserveringen</h2>
            </div>
            
            <?php if (count($reservations) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Printer</th>
                                <th>Start Tijd</th>
                                <th>Eind Tijd</th>
                                <th>PIN Code</th>
                                <th>Filament</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation): ?>
                                <?php
                                    $now = new DateTime();
                                    $start = new DateTime($reservation['Pr_Start']);
                                    $end = new DateTime($reservation['Pr_End']);
                                    
                                    if ($now < $start) {
                                        $status = 'Gepland';
                                        $statusClass = 'status-upcoming';
                                    } elseif ($now >= $start && $now <= $end) {
                                        $status = 'Actief';
                                        $statusClass = 'status-active';
                                    } else {
                                        $status = 'Voltooid';
                                        $statusClass = 'status-completed';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reservation['PrinterNaam']); ?></td>
                                    <td><?php echo htmlspecialchars(formatDateTime($reservation['Pr_Start'])); ?></td>
                                    <td><?php echo htmlspecialchars(formatDateTime($reservation['Pr_End'])); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['Pin']); ?></td>
                                    <td>
                                        <?php if ($reservation['Filament_Kleur'] || $reservation['Filament_Type']): ?>
                                            <?php echo htmlspecialchars($reservation['Filament_Kleur'] . ' ' . $reservation['Filament_Type']); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $status; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>Je hebt nog geen reserveringen.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <footer>
        <p>&copy; 2025 MaakLab Badge Systeem | Ontwikkeld door Lars Van der Kerkhove</p>
    </footer>
</body>
</html>