<?php
// Start the session
session_start();

// Base URL for API calls
$apiBaseUrl = ""; // Modify this based on your setup if needed

// Function to make API calls
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

// Get all printers from the API
$printers = callAPI('GET', '/api/printer_api.php', null);
$reservations = callAPI('GET', '/api/reservatie_api.php', null);
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

    <!-- Externe CSS en JS voor kalender -->
    <link href="Styles/reservatie.css" rel="stylesheet">
    <script src="Scripts/reservatie.js"></script>
    <script src="Scripts/auth.js"></script>
    <script src="Scripts/navigation.js"></script>

    <!-- Externe CSS voor de layout -->
    <link rel="stylesheet" href="Styles/mystyle.css">
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
                    <a href="mijnKalender.html" class="nav-link">
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

    <div class="container">
        <!-- Updated form-container section with two-step form structure -->
        <div class="form-container">
            <!-- Stap 1: Basisgegevens -->
            <div id="step1Container" class="form-step">
                <div class="input-field">
                    <label for="printer">Printer:</label>
                    <select id="printer">
                        <?php if (isset($printers['data']['data']) && is_array($printers['data']['data'])): ?>
                            <?php foreach ($printers['data']['data'] as $printer): ?>
                                <?php if ($printer['Status'] === 'Beschikbaar'): ?>
                                    <option value="printer<?= $printer['Printer_ID'] ?>" data-printer-id="<?= $printer['Printer_ID'] ?>">
                                        <?= htmlspecialchars($printer['Naam']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="noPrinters">Geen beschikbare printers</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="input-field">
                    <label for="date">Datum:</label>
                    <input type="date" id="date" onchange="onDateChange()">
                </div>

                <div class="input-field">
                    <label for="eventName">Naam:</label>
                    <input type="text" id="eventName" placeholder="Voer je naam in">
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
                    <input type="text" id="customFilament" placeholder="Voer het filamenttype in..." style="display: none;" />    
                </div>
                
                <div class="input-field">
                    <label for="filamentColor">Kleur filament</label>
                    <select id="filamentColor">
                        <option value="Zwart">Zwart</option>
                        <option value="Wit">Wit</option>
                        <option value="Blauw">Blauw</option>
                        <option value="Rood">Rood</option>
                        <option value="Geel">Geel</option>
                        <option value="Andere">Andere...</option>
                    </select>
                    <input type="text" id="customColor" placeholder="kies een kleur ..." style="display: none;" />
                </div>
                
                <div class="input-field">
                    <label for="filamentWeight">Hoeveelheid filament (gram)</label>
                    <input type="number" id="filamentWeight" name="filamentWeight" min="1" step="1" placeholder="Gram">
                </div>
                
                <div class="input-field">
                    <button type="button" id="submitReservation" class="btn">Reservatie toevoegen</button>
                </div>
            </div>
        </div>

        <div class="timeline-container">
            <div class="rooms">
                <div> </div>
                <?php if (isset($printers['data']['data']) && is_array($printers['data']['data'])): ?>
                    <?php foreach ($printers['data']['data'] as $printer): ?>
                        <div><?= htmlspecialchars($printer['Naam']) ?></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div>Geen printers beschikbaar</div>
                <?php endif; ?>
            </div>

            <!-- Timeline Grid -->
            <div class="timeline">
                <!-- Time Row -->
                <div class="timeline-row"></div>

                <!-- Printer Rows -->
                <?php if (isset($printers['data']['data']) && is_array($printers['data']['data'])): ?>
                    <?php foreach ($printers['data']['data'] as $printer): ?>
                        <div class="timeline-row" id="printer<?= $printer['Printer_ID'] ?>"></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="Scripts/algemene.js"></script>

    <script>
        // Global variables to store data from the API
        let apiPrinters = <?= json_encode(isset($printers['data']['data']) ? $printers['data']['data'] : []); ?>;
        let apiReservations = <?= json_encode(isset($reservations['data']['data']) ? $reservations['data']['data'] : []); ?>;
        let events = []; // Will store formatted events for the calendar

        // Convert API reservations to the format needed for the calendar
        function convertReservationsToEvents() {
            events = [];
            
            if (apiReservations && apiReservations.length > 0) {
                apiReservations.forEach(res => {
                    // Parse start and end times from the API format
                    const startDateTime = new Date(res.Pr_Start);
                    const endDateTime = new Date(res.Pr_End);
                    
                    // Extract hours as decimal for the calendar
                    const startHour = startDateTime.getHours() + startDateTime.getMinutes() / 60;
                    const endHour = endDateTime.getHours() + endDateTime.getMinutes() / 60;
                    
                    // Format date as YYYY-MM-DD
                    const date = startDateTime.toISOString().split('T')[0];
                    
                    // Assign a random color based on the reservation ID for consistency
                    const colors = ["blue", "green", "orange", "red"];
                    const colorIndex = res.Reservatie_ID % colors.length;
                    
                    // Create an event object in the format expected by the calendar
                    events.push({
                        printer: 'printer' + res.Printer_ID,
                        title: res.Voornaam + ' ' + res.Naam,
                        date: date,
                        start: startHour,
                        end: endHour,
                        color: colors[colorIndex],
                        opo: res.Comment || 'Geen OPO/project',
                        filamentType: res.Filament_Type || 'Niet gespecificeerd',
                        filamentColor: res.Filament_Kleur || 'Niet gespecificeerd',
                        filamentWeight: res.Filament_Weight || '0'
                    });
                });
            }
            
            return events;
        }

        // Modified function to handle API data
        function onDateChange() {
            const selectedDate = document.getElementById("date").value;
            
            // First convert API reservations to events format
            convertReservationsToEvents();
            
            // Clear previous events and render for selected date
            clearEvents();
            renderEvents(selectedDate);
        }

        // Submit a new reservation to the API
        function submitReservationToAPI() {
            // Get the form values
            const printerId = document.getElementById("printer").selectedOptions[0].dataset.printerId;
            const eventName = document.getElementById("eventName").value;
            const selectedDate = document.getElementById("date").value;
            const startTime = document.getElementById("startTime").value;
            const printDuration = document.getElementById("printDuration").value;
            const opo = document.getElementById("opoInput").value;
            const filamentType = document.getElementById("filamentType").value === "Andere" ? 
                                document.getElementById("customFilament").value : 
                                document.getElementById("filamentType").value;
            const filamentColor = document.getElementById("filamentColor").value === "Andere" ? 
                                 document.getElementById("customColor").value : 
                                 document.getElementById("filamentColor").value;
            const filamentWeight = document.getElementById("filamentWeight").value;

            // Calculate start and end datetimes
            const startHour = Math.floor(parseFloat(startTime));
            const startMinute = Math.round((parseFloat(startTime) - startHour) * 60);
            const durationHours = parseFloat(printDuration);
            
            const startDateTime = new Date(selectedDate);
            startDateTime.setHours(startHour, startMinute, 0);
            
            const endDateTime = new Date(selectedDate);
            endDateTime.setHours(startHour + Math.floor(durationHours));
            endDateTime.setMinutes(startMinute + Math.round((durationHours - Math.floor(durationHours)) * 60));
            
            // Format dates for API
            const formatDateForAPI = (date) => {
                return date.toISOString().slice(0, 19).replace('T', ' ');
            };
            
            // Create the data object for the API
            const data = {
                User_ID: 1, // This should be the logged-in user's ID
                Printer_ID: printerId,
                Pr_Start: formatDateForAPI(startDateTime),
                Pr_End: formatDateForAPI(endDateTime),
                Comment: opo,
                Filament_Type: filamentType,
                Filament_Kleur: filamentColor,
                Filament_Weight: filamentWeight
            };

            // Send the data to the API
            fetch('/api/reservatie_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.status === 201) {
                    // Success - refresh the calendar
                    alert("Reservatie succesvol toegevoegd!");
                    
                    // Refresh API data
                    fetchReservations().then(() => {
                        // Clear and redraw calendar
                        onDateChange();
                        
                        // Reset form and go back to step 1
                        resetForm();
                    });
                } else {
                    // Error
                    alert("Fout bij toevoegen reservatie: " + result.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("Er is een fout opgetreden bij het toevoegen van de reservatie.");
            });
        }

        // Function to fetch reservations from the API
        function fetchReservations() {
            return fetch('/api/reservatie_api.php')
                .then(response => response.json())
                .then(result => {
                    if (result.status === 200) {
                        apiReservations = result.data;
                        convertReservationsToEvents();
                    } else {
                        console.error("Error fetching reservations:", result.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        // Reset the form after submission
        function resetForm() {
            document.getElementById("eventName").value = "";
            document.getElementById("opoInput").value = "";
            document.getElementById("filamentType").selectedIndex = 0;
            document.getElementById("filamentColor").selectedIndex = 0;
            document.getElementById("customFilament").style.display = "none";
            document.getElementById("customColor").style.display = "none";
            document.getElementById("filamentWeight").value = "";
            
            // Go back to step 1
            document.getElementById("step1Container").style.display = "block";
            document.getElementById("step2Container").style.display = "none";
        }

        // Modify the existing addReservation function to call the API
        function addReservation() {
            // Validation for step 2 fields
            const opo = document.getElementById("opoInput").value;
            const filamentType = document.getElementById("filamentType").value;
            const filamentColor = document.getElementById("filamentColor").value;
            const filamentWeight = document.getElementById("filamentWeight").value;
            
            if (!opo.trim()) {
                alert("Voer OPO/projectnaam in. indien geen OPO/project, vul 'geen' in.");
                return;
            }
            
            if (!filamentType) {
                alert("Kies type filament.");
                return;
            }
            
            if (!filamentColor.trim()) {
                alert("kies filament kleur.");
                return;
            }
            
            if (!filamentWeight || filamentWeight <= 0) {
                alert("Voer geldige hoeveelheid filament in (gram).");
                return;
            }
            
            // All validations passed, submit to API
            submitReservationToAPI();
        }

        // Function to go back to step 1
        function goToStep1() {
            document.getElementById("step1Container").style.display = "block";
            document.getElementById("step2Container").style.display = "none";
        }

        // Initialize everything when the page loads
        document.addEventListener('DOMContentLoaded', () => {
            const filamentSelect = document.getElementById("filamentType");
            const customFilamentField = document.getElementById("customFilament");
            const colorSelect = document.getElementById("filamentColor");
            const colorField = document.getElementById("customColor");

            // Event handlers for custom filament type and color
            filamentSelect.addEventListener("change", function() {
                customFilamentField.style.display = (filamentSelect.value === "Andere") ? "block" : "none";
            });

            colorSelect.addEventListener("change", function() {
                colorField.style.display = (colorSelect.value === "Andere") ? "block" : "none";
            });

            // Add event listeners for navigation buttons
            document.getElementById("nextToStep2").addEventListener("click", goToStep2);
            document.getElementById("submitReservation").addEventListener("click", addReservation);
            
            // Set today's date as default
            const today = new Date().toISOString().split('T')[0];
            document.getElementById("date").value = today;
            
            // Initialize time options and grid
            generateTimeOptions();
            updateTimeSlots();
            
            // Load events for today
            onDateChange();
        });
    </script>
</body>
</html>