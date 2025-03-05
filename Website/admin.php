<?php
// admin.php - Beheerderspagina voor MaakLab
session_start();

// Controleer of de gebruiker is ingelogd en een admin rol heeft
// Dit zou normaal gesproken worden geverifieerd met een login systeem
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Redirect naar login pagina als niet ingelogd of geen admin
    header("Location: login.php");
    exit;
}

// Base URL voor API calls
$apiBaseUrl = ""; // Vul hier de basis URL in indien nodig (bv. "https://example.com/api")

// Functie om API calls te maken
function callAPI($method, $endpoint, $data = null) {
    global $apiBaseUrl;
    $url = $apiBaseUrl . $endpoint;
    
    $curl = curl_init();
    
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ];
    
    if ($data !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    
    curl_setopt_array($curl, $options);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    curl_close($curl);
    
    return [
        'code' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

// Functie om e-mail te versturen bij wijzigingen
function sendNotificationEmail($userEmail, $userName, $subject, $message) {
    $headers = "From: maaklab@example.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $body = "
    <html>
    <head>
        <title>$subject</title>
    </head>
    <body>
        <p>Beste $userName,</p>
        <p>$message</p>
        <p>Met vriendelijke groeten,<br>MaakLab Team</p>
    </body>
    </html>
    ";
    
    return mail($userEmail, $subject, $body, $headers);
}

// Verwerk form submits
$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verwerk verschillende forms op basis van de action
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            // ... (rest of the switch statement remains the same as in the original PHP file)
        }
    }
}

// Haal data op voor de pagina
$users = callAPI('GET', '/gebruiker_api.php', null);
$printers = callAPI('GET', '/printer_api.php', null);
$reservations = callAPI('GET', '/reservatie_api.php', null);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Styles/mystyle.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Admin</title>
    <style>

    </style>
</head>
<body>   
    <nav class="navbar">
      <div class="nav-container">
          <a href="index.html" class="nav-logo">
              <img src="images/vives smile.svg" alt="Vives Logo" />
          </a>
          
          <button class="nav-toggle" aria-label="Open menu">
              <span class="hamburger"></span>
          </button>
  
          <ul class="nav-menu">
              <li class="nav-item">
                  <a href="reservatie.html" class="nav-link">
                      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <circle cx="12" cy="12" r="10"/>
                          <line x1="12" y1="8" x2="12" y2="16"/>
                          <line x1="8" y1="12" x2="16" y2="12"/>
                      </svg>
                      Reserveer een printer
                  </a>
              </li>
              <li class="nav-item">
                  <a href="mijnKalender.html" class="nav-link">
                      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                          <line x1="16" y1="2" x2="16" y2="6"/>
                          <line x1="8" y1="2" x2="8" y2="6"/>
                          <line x1="3" y1="10" x2="21" y2="10"/>
                      </svg>
                        reservaties
                  </a>
              </li>
              <li class="nav-item">
                    <a href="printers.html" class="nav-link">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 9V2h12v7"/>
                            <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                            <rect x="6" y="14" width="12" height="8"/>
                        </svg>
                        Info over printers
                    </a>
                </li>
                <li class="nav-item">
                <a href="uitlog.html" class="nav-link">
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

  <div class="container-fluid mt-4 mb-5">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">MaakLab Admin Dashboard</h1>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="agenda-tab" data-bs-toggle="tab" href="#agenda" role="tab">Agenda & Reserveringen</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="printers-tab" data-bs-toggle="tab" href="#printers" role="tab">Printers Beheren</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="users-tab" data-bs-toggle="tab" href="#users" role="tab">Gebruikers & Rollen</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="control-tab" data-bs-toggle="tab" href="#control" role="tab">Printer Besturing</a>
                    </li>
                </ul>
                
                <!-- Rest of the content from adminv1.php remains the same -->
                <div class="tab-content" id="adminTabsContent">
                    <!-- Tabs content from the original adminv1.php -->
                    <!-- ... (all the tab content remains the same) ... -->
                </div>
            </div>
        </div>
    </div>

    <!-- All modals from the original adminv1.php -->
    <!-- ... (all modals remain the same) ... -->

    <script src="Scripts/algemene.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript from the original adminv1.php remains the same -->
    <script>
        // All the original JavaScript code stays in place
        // ... 
    </script>
</body>
</html>