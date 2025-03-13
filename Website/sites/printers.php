<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Printer Information</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #333;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .user-info {
            font-size: 14px;
        }
        .printer-list {
            margin-top: 20px;
        }
        .printer-card {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .printer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .printer-name {
            font-size: 20px;
            font-weight: bold;
        }
        .printer-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
        }
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-maintenance {
            background-color: #fff3cd;
            color: #856404;
        }
        .printer-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .detail-item {
            margin-bottom: 10px;
        }
        .detail-label {
            font-weight: bold;
            color: #555;
        }
        .refresh-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 20px;
        }
        .refresh-btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Printer Management System</h1>
        <div class="user-info">
            <div>Date: <?php echo date('Y-m-d H:i:s'); ?></div>
            <div>User: <?php echo htmlspecialchars($_SERVER['PHP_AUTH_USER'] ?? 'Alexatkind'); ?></div>
        </div>
    </div>

    <div class="container">
        <h2>Available Printers</h2>
        
        <div class="printer-list" id="printerList">
            <!-- Printer information will be loaded here via JavaScript -->
            <div class="loading">Loading printer information...</div>
        </div>
        
        <button class="refresh-btn" onclick="loadPrinters()">Refresh Printers</button>
    </div>

    <script>
        // Function to load printers from the API
        function loadPrinters() {
            const printerList = document.getElementById('printerList');
            printerList.innerHTML = '<div class="loading">Loading printer information...</div>';
            
            fetch('printer_api.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success' && data.data && data.data.length > 0) {
                        printerList.innerHTML = '';
                        
                        data.data.forEach(printer => {
                            const printerCard = document.createElement('div');
                            printerCard.className = 'printer-card';
                            
                            // Determine status class
                            let statusClass = '';
                            switch(printer.Status.toLowerCase()) {
                                case 'active':
                                    statusClass = 'status-active';
                                    break;
                                case 'inactive':
                                    statusClass = 'status-inactive';
                                    break;
                                case 'maintenance':
                                    statusClass = 'status-maintenance';
                                    break;
                                default:
                                    statusClass = '';
                            }
                            
                            printerCard.innerHTML = `
                                <div class="printer-header">
                                    <div class="printer-name">${printer.Naam}</div>
                                    <div class="printer-status ${statusClass}">${printer.Status}</div>
                                </div>
                                <div class="printer-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Printer ID:</div>
                                        <div>${printer.Printer_ID}</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Last Status Change:</div>
                                        <div>${printer.Laatste_Status_Change}</div>
                                    </div>
                                    <div class="detail-item" style="grid-column: 1 / span 2;">
                                        <div class="detail-label">Additional Info:</div>
                                        <div>${printer.Info || 'No additional information'}</div>
                                    </div>
                                </div>
                                <div class="printer-actions" style="margin-top: 15px;">
                                    <button onclick="viewPrinterDetails(${printer.Printer_ID})">View Details</button>
                                </div>
                            `;
                            
                            printerList.appendChild(printerCard);
                        });
                    } else {
                        printerList.innerHTML = '<div>No printers found</div>';
                    }
                })
                .catch(error => {
                    printerList.innerHTML = `<div>Error loading printers: ${error.message}</div>`;
                    console.error('Error fetching printer data:', error);
                });
        }
        
        // Function to view detailed information about a specific printer
        function viewPrinterDetails(printerId) {
            fetch(`printer_api.php?id=${printerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(`Detailed information for printer ${data.data.Naam}:\n\n` +
                              `ID: ${data.data.Printer_ID}\n` +
                              `Status: ${data.data.Status}\n` +
                              `Last Status Change: ${data.data.Laatste_Status_Change}\n` +
                              `Info: ${data.data.Info || 'No additional information'}`);
                    } else {
                        alert('Failed to load printer details');
                    }
                })
                .catch(error => {
                    alert('Error fetching printer details');
                    console.error('Error:', error);
                });
        }
        
        // Load printers when the page loads
        document.addEventListener('DOMContentLoaded', loadPrinters);
    </script>
</body>
</html>