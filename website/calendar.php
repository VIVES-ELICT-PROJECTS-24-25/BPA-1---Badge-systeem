<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

$currentPage = 'calendar';
$pageTitle = 'Reserveringskalender - 3D Printer Reserveringssysteem';

// Get all printers for resources
$stmt = $conn->query("SELECT Printer_ID as id, Versie_Toestel as name, Status as status FROM Printer");
$printers = $stmt->fetchAll();

// Get selected printer filter
$selectedPrinter = isset($_GET['printer_id']) ? intval($_GET['printer_id']) : 0;

// Get the current date for default view
$today = date('Y-m-d');

// Get date range from URL parameters or use defaults
$start = isset($_GET['start']) ? $_GET['start'] : $today;
$end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d', strtotime('+30 days'));

// Get reservations for the selected period
$stmt = $conn->prepare("
    SELECT r.Reservatie_ID as id, r.Printer_ID as resourceId, r.PRINT_START as start, r.PRINT_END as end, 
           p.Versie_Toestel as title, r.User_ID as user_id, 'reservation' as eventType
    FROM Reservatie r
    JOIN Printer p ON r.Printer_ID = p.Printer_ID
    WHERE DATE(r.PRINT_START) <= ? AND DATE(r.PRINT_END) >= ?
    ORDER BY r.PRINT_START
");
$stmt->execute([$end, $start]);
$reservations = $stmt->fetchAll();

// Get opening hours for the selected period
$stmt = $conn->prepare("
    SELECT id, Tijdstip_start as start, Tijdstip_einde as end, 
           'Openingsuren' as title, Lokaal_id as lokaalId, 'openingshours' as eventType
    FROM Openingsuren
    WHERE DATE(Tijdstip_start) <= ? AND DATE(Tijdstip_einde) >= ?
    ORDER BY Tijdstip_start
");
$stmt->execute([$end, $start]);
$openingsuren = $stmt->fetchAll();

// Get all locations
$stmt = $conn->query("SELECT id, Locatie FROM Lokalen ORDER BY Locatie");
$lokalen = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Reserveringskalender</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Kalender</li>
            </ol>
        </nav>
    </div>
    
    <!-- Opening Hours List -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Openingsuren</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Start Tijd</th>
                            <th>Eind Tijd</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($openingsuren as $opening): ?>
                            <tr>
                                <td><?= date('l, d F Y', strtotime($opening['start'])) ?></td>
                                <td><?= date('H:i', strtotime($opening['start'])) ?></td>
                                <td><?= date('H:i', strtotime($opening['end'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($openingsuren)): ?>
                            <tr>
                                <td colspan="3" class="text-center">Geen openingsuren gevonden voor deze periode.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Legenda</h5>
        </div>
        <div class="card-body">
            <div class="d-flex gap-4 flex-wrap">
                <div>
                    <span class="badge bg-primary me-2" style="width: 30px;">&nbsp;</span>
                    <span>Reserveringen</span>
                </div>
                
                <?php foreach ($lokalen as $lokaal): ?>
                <div>
                    <span class="badge bg-secondary me-2"><?= htmlspecialchars($lokaal['Locatie']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div id="calendar"></div>
        </div>
    </div>
</div>

<!-- Reservation Details Modal -->
<div class="modal fade" id="reservationModal" tabindex="-1" aria-labelledby="reservationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reservationModalLabel">Reservering Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="reservationDetails">
                    <p><strong>Printer:</strong> <span id="printer-name"></span></p>
                    <p><strong>Reservering:</strong> <span id="reservation-id"></span></p>
                    <p><strong>Start tijd:</strong> <span id="start-time"></span></p>
                    <p><strong>Eind tijd:</strong> <span id="end-time"></span></p>
                </div>
            </div>
            <div class="modal-footer">
                <?php if (isset($_SESSION['User_ID'])): ?>
                    <a href="#" id="view-reservation-btn" class="btn btn-primary">Details Bekijken</a>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Sluiten</button>
            </div>
        </div>
    </div>
</div>

<!-- Opening Hours Details Modal -->
<div class="modal fade" id="openingHoursModal" tabindex="-1" aria-labelledby="openingHoursModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="openingHoursModalLabel">Openingsuren Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="openingHoursDetails">
                    <p><strong>Locatie:</strong> <span id="location-name"></span></p>
                    <p><strong>Start tijd:</strong> <span id="opening-start-time"></span></p>
                    <p><strong>Eind tijd:</strong> <span id="opening-end-time"></span></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Sluiten</button>
            </div>
        </div>
    </div>
</div>

<!-- Include FullCalendar -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    
    var printers = <?php echo json_encode($printers); ?>;
    var reservations = <?php echo json_encode($reservations); ?>;
    var openingsuren = <?php echo json_encode($openingsuren); ?>;
    
    // Create calendar resources (printers and openingsuren)
    var resources = printers.map(function(printer) {
        return {
            id: printer.id,
            title: printer.name,
            status: printer.status,
            eventResourceEditable: false // Prevent dragging events between resources
        };
    });
    
    // Process opening hours to make them visible
    var openingEvents = [];
    openingsuren.forEach(function(opening) {
        // For each opening hour, create events for all resources (printers)
        printers.forEach(function(printer) {
            // Create a foreground event with prominent styling
            openingEvents.push({
                id: 'opening-' + opening.id + '-printer-' + printer.id,
                resourceId: printer.id,
                start: opening.start,
                end: opening.end,
                title: 'OPENINGSUREN',
                color: '#28a745',
                textColor: 'white',
                borderColor: '#28a745',
                classNames: ['openingsuur-event'],
                extendedProps: {
                    type: 'openingsuur',
                    lokaalId: opening.lokaalId
                }
            });
        });
    });
    
    // Process reservations
    var reservationEvents = reservations.map(function(res) {
        return {
            id: res.id,
            resourceId: res.resourceId,
            start: res.start,
            end: res.end,
            title: res.title,
            color: '#007bff', // Bootstrap primary color
            extendedProps: {
                type: 'reservation',
                user_id: res.user_id
            }
        };
    });
    
    // Combine all events
    var allEvents = [...openingEvents, ...reservationEvents];
    
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'resourceTimeGridWeek',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'openingsuren resourceTimeGridDay,resourceTimeGridWeek,dayGridMonth'
        },
        customButtons: {
            openingsuren: {
                text: 'Openingsuren',
                click: function() {
                    // Toggle visibility of reservation events
                    document.querySelectorAll('.fc-event:not(.opening-hour-event)').forEach(function(el) {
                        if (el.style.display === 'none') {
                            el.style.display = '';
                        } else {
                            el.style.display = 'none';
                        }
                    });
                    
                    // Toggle button class to show active state
                    document.querySelector('.fc-openingsuren-button').classList.toggle('active');
                }
            }
        },
        resources: resources,
        events: allEvents,
        slotMinTime: '08:00:00',
        slotMaxTime: '20:00:00',
        allDaySlot: false,
        height: 'auto',
        eventClick: function(info) {
            if (info.event.extendedProps.type === 'openingsuur') {
                // Handle opening hours click
                document.getElementById('location-name').textContent = info.event.title;
                document.getElementById('opening-start-time').textContent = new Date(info.event.start).toLocaleString();
                document.getElementById('opening-end-time').textContent = new Date(info.event.end).toLocaleString();
                
                var modal = new bootstrap.Modal(document.getElementById('openingHoursModal'));
                modal.show();
            } else if (info.event.display !== 'background') {
                // Handle reservation click
                document.getElementById('printer-name').textContent = info.event.title;
                document.getElementById('reservation-id').textContent = 'ID: ' + info.event.id;
                document.getElementById('start-time').textContent = new Date(info.event.start).toLocaleString();
                document.getElementById('end-time').textContent = new Date(info.event.end).toLocaleString();
                
                // Set link to view reservation details
                var viewBtn = document.getElementById('view-reservation-btn');
                if (viewBtn) {
                    viewBtn.href = 'reservation-details.php?id=' + info.event.id;
                }
                
                var modal = new bootstrap.Modal(document.getElementById('reservationModal'));
                modal.show();
            }
        },
        eventDidMount: function(info) {
            // Add a tooltip for opening hours
            if (info.event.extendedProps.type === 'openingsuur') {
                // Add tooltip functionality
                var tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.innerHTML = 'Openingsuren<br>' + 
                                   new Date(info.event.start).toLocaleTimeString() + ' - ' + 
                                   new Date(info.event.end).toLocaleTimeString();
                tooltip.style.position = 'absolute';
                tooltip.style.zIndex = 10000;
                tooltip.style.backgroundColor = '#28a745';
                tooltip.style.color = 'white';
                tooltip.style.padding = '5px';
                tooltip.style.borderRadius = '5px';
                tooltip.style.fontSize = '12px';
                tooltip.style.boxShadow = '0 0 5px rgba(0,0,0,0.2)';
                tooltip.style.display = 'none';
                
                document.body.appendChild(tooltip);
                
                var eventEl = info.el;
                
                eventEl.addEventListener('mouseover', function() {
                    tooltip.style.display = 'block';
                    tooltip.style.left = (eventEl.getBoundingClientRect().left + window.scrollX) + 'px';
                    tooltip.style.top = (eventEl.getBoundingClientRect().top + window.scrollY - 30) + 'px';
                });
                
                eventEl.addEventListener('mouseout', function() {
                    tooltip.style.display = 'none';
                });
                
                // Make cursor indicate clickable
                eventEl.style.cursor = 'pointer';
            }
        }
    });
    
    calendar.render();
});
</script>

<style>
/* Style for opening hours events */
.fc-event.openingsuur-event {
    opacity: 1 !important;
    font-weight: bold !important;
    text-align: center !important;
    box-shadow: 0 0 5px rgba(0,0,0,0.2) !important;
    border: 2px solid #28a745 !important;
    z-index: 5 !important; /* Make sure opening hours are on top */
}

/* Make sure opening hours are visible */
.fc-event {
    z-index: 3 !important;
}

/* Custom styled opening hours button */
.fc-openingsuren-button {
    background-color: #28a745 !important;
    border-color: #28a745 !important;
    color: white !important;
}

/* Opening hours button active state */
.fc-openingsuren-button.active {
    background-color: #218838 !important;
    box-shadow: inset 0 0 5px rgba(0,0,0,0.2) !important;
}
</style>

<?php include 'includes/footer.php'; ?>