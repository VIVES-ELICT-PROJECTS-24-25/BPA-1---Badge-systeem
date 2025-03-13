<?php
// Start session to manage user authentication
session_start();

// Check if user is logged in - redirect to login page if not
if (!isset($_SESSION['user_id'])) {
    // Uncomment the following line when your login system is ready
    // header('Location: login.php');
    // exit;
    
    // For development, we'll set a mock user
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'Alexatkind';
    $_SESSION['voornaam'] = 'Alex';
    $_SESSION['naam'] = 'Atkind';
}

// Include configuration
require_once 'sites/config.php';

// Function to get printers from the API
function getPrinters() {
    $conn = getConnection();
    $query = "SELECT * FROM Printer";
    $result = $conn->query($query);
    
    $printers = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $printers[] = $row;
        }
    }
    
    $conn->close();
    return $printers;
}

// Get all printers for the form
$printers = getPrinters();

// Current date in the required format for date input
$currentDate = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservatie</title>
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- External CSS -->
    <link href="Styles/kalender.css" rel="stylesheet">
    <link rel="stylesheet" href="Styles/mystyle.css">
    <link rel="stylesheet" href="Styles/reservatie.css">

    <!-- External JavaScript -->
    <script src="Scripts/auth.js"></script>
    <script src="Scripts/navigation.js"></script>
</head>
<body>

    <nav class="navbar">
    <div class="nav-container">
        <a href="login.php" class="nav-logo">
            <img src="images/vives smile.svg" alt="Vives Logo" />
        </a>
        
        <button class="nav-toggle" aria-label="Open menu">
            <span class="hamburger"></span>
        </button>

        <ul class="nav-menu">
            <li class="nav-item">
                <a href="reservatie.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="16"/>
                        <line x1="8" y1="12" x2="16" y2="12"/>
                    </svg>
                    Reserveer een printer
                </a>
            </li>
            <li class="nav-item">
                <a href="mijnKalender.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    Mijn reservaties
                </a>
            </li>
            <li class="nav-item">
                <a href="printers.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 9V2h12v7"/>
                        <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                        <rect x="6" y="14" width="12" height="8"/>
                    </svg>
                    Info over printers
                </a>
            </li>
            <li class="nav-item">
                <a href="uitlog.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Log uit
                </a>
            </li>
        </ul>
    </div>
</nav>

  <div class="container">
    <!-- User info display -->
    <div class="user-info">
        <span>Ingelogd als: <span id="current-user"><?php echo htmlspecialchars($_SESSION['username']); ?></span></span>
        <input type="hidden" id="user_id" value="<?php echo $_SESSION['user_id']; ?>">
    </div>
    
    <!-- Message container for success/error messages -->
    <div id="messageContainer"></div>
    
    <!-- Updated form-container section with two-step form structure -->
    <div class="form-container">
        <!-- Stap 1: Basisgegevens -->
        <div id="step1Container" class="form-step">
            <div class="input-field">
                <label for="printer">Printer:</label>
                <select id="printer">
                    <?php foreach($printers as $printer): ?>
                        <option value="<?php echo $printer['Printer_ID']; ?>">
                            <?php echo htmlspecialchars($printer['Naam']) . ' (' . htmlspecialchars($printer['Status']) . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="input-field">
                <label for="date">Datum:</label>
                <input type="date" id="date" value="<?php echo $currentDate; ?>" min="<?php echo $currentDate; ?>">
            </div>

            <div class="input-field">
                <label for="eventName">Naam:</label>
                <input type="text" id="eventName" value="<?php echo htmlspecialchars($_SESSION['voornaam'] . ' ' . $_SESSION['naam']); ?>" readonly>
            </div>

            <div class="input-field">
                <label for="startTime">Start tijd:</label>
                <select id="startTime"></select>
            </div>

            <div class="input-field">
                <label for="printDuration">Print tijd (uren):</label>
                <input type="number" id="printDuration" min="0.25" max="12" step="0.25" value="1">
            </div>

            <div class="input-field">
                <button type="button" id="nextToStep2" class="btn">Volgende</button>
            </div>
        </div>

        <!-- Stap 2: Extra gegevens -->
        <div id="step2Container" class="form-step" style="display: none;">
            <div class="input-field">
                <label for="opoInput">OPO/Project</label>
                <input type="text" id="opoInput" name="opoInput" placeholder="Voer OPO in, of projectnaam.">
            </div>
            
            <div class="input-field">
                <label for="filamentType">Type filament</label>
                <select id="filamentType">
                    <option value="PLA">PLA</option>
                    <option value="ABS">ABS</option>
                    <option value="PETG">PETG</option>
                    <option value="Andere">Andere...</option>
                </select>
                <input type="text" id="customFilament" placeholder="Voer het filamenttype in..." />    
            </div>
            
            <div class="input-field">
                <label for="filamentColor">Kleur filament</label>
                <select id="filamentColor">
                </select>
                <input type="text" id="customColor" placeholder="kies een kleur ..." />
            </div>
            
            <div class="input-field">
                <label for="filamentWeight">Hoeveelheid filament (gram)</label>
                <input type="number" id="filamentWeight" name="filamentWeight" min="1" step="1" placeholder="Gram">
            </div>
            
            <div class="input-field">
                <button type="button" id="backToStep1" class="btn" style="background-color: #888;">Terug</button>
                <button type="button" id="submitReservation" class="btn">Reservatie toevoegen</button>
            </div>
        </div>
    </div>

    <div class="timeline-container">
        <div class="rooms">
            <div> </div>
            <?php foreach($printers as $printer): ?>
                <div><?php echo htmlspecialchars($printer['Naam']); ?></div>
            <?php endforeach; ?>
        </div>

        <!-- Timeline Grid -->
        <div class="timeline">
            <!-- Time Row -->
            <div class="timeline-row" id="timeHeader"></div>

            <!-- Printer Rows -->
            <?php foreach($printers as $index => $printer): ?>
                <div class="timeline-row" id="printer<?php echo $printer['Printer_ID']; ?>"></div>
            <?php endforeach; ?>
        </div>
    </div>
  </div>
  
  <!-- Pass PHP data to JavaScript -->
  <script>
    // Current user information from PHP session
    const currentUser = {
        userId: <?php echo $_SESSION['user_id']; ?>,
        name: '<?php echo addslashes($_SESSION['username']); ?>'
    };
    
    // Pass printers data to JavaScript
    const initialPrinters = <?php echo json_encode($printers); ?>;
  </script>
  
  <!-- Include the reservation script -->
  <script src="Scripts/reservatie.js"></script>
</body>
</html>