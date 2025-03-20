<?php
$api_url = 'https://3dprintersmaaklabvives.be/api_test/printer_api.php';

function getPrinterData($url) {
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => [
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) return ['success' => false, 'message' => 'Kan geen verbinding maken met de API'];
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) return ['success' => false, 'message' => 'Ongeldige JSON-response'];
    
    return ['success' => true, 'data' => $data['data'] ?? []];
}

function getStatusColor($status) {
    $status = strtolower($status);
    if ($status === 'beschikbaar') return '#28a745';
    if ($status === 'gereserveerd') return '#007bff';
    if ($status === 'in onderhoud') return '#ffa500';
    return '#dc3545';
}

$result = getPrinterData($api_url);
$printers = $result['success'] ? $result['data'] : [];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Printer Overzicht</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../Styles/printer.css">
    <link rel="stylesheet" href="../Styles/mystyle.css">

</head>
<body>
<nav class="navbar">
        <div class="nav-container">
            <a href="index.html" class="nav-logo">
                <img src="../images/vives smile.svg" alt="Vives Logo" />
            </a>
            
            <button class="nav-toggle" aria-label="Open menu">
                <span class="hamburger"></span>
            </button>

        
                    </a>
                </li>
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
        <h1>Printer Overzicht</h1>
        <?php if (!$result['success']): ?>
            <div class="error">
                <p><?php echo $result['message']; ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($printers as $printer): ?>
                <?php
                if (!is_array($printer)) continue;
                $printerName = $printer['Naam'] ?? '(Onbekend)';
                $printerStatus = $printer['Status'] ?? 'Onbekend';
                $printerInfo = $printer['Info'] ?? 'Geen extra informatie beschikbaar';
                $statusColor = getStatusColor($printerStatus);
                ?>
             <div class="printer-card" data-status="<?php echo strtolower(htmlspecialchars($printerStatus)); ?>">
    <h2 class="printer-name"><?php echo htmlspecialchars($printerName); ?></h2>
    <div class="printer-header" style="background-color: <?php echo $statusColor; ?>;">
        <?php echo htmlspecialchars($printerStatus); ?>
    </div>
    <div class="printer-body">
        <div class="printer-info">
            <p><?php echo htmlspecialchars($printerInfo); ?></p>
        </div>
    </div>
</div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Detecteer overlopende tekst
    const infoElements = document.querySelectorAll('.printer-info');
    
    infoElements.forEach(function(element) {
        if (element.scrollHeight > element.clientHeight) {
            element.classList.add('has-overflow');
        }
    });
});
</script>