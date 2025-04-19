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

// Get the current date for default view
$today = date('Y-m-d');

// Get date range from URL parameters or use defaults
$start = isset($_GET['start']) ? $_GET['start'] : $today;
$end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d', strtotime('+30 days'));

// Get reservations for the selected period
$stmt = $conn->prepare("
    SELECT r.Reservatie_ID as id, r.Printer_ID as resourceId, r.PRINT_START as start, r.PRINT_END as end, 
           p.Versie_Toestel as title, r.User_ID as user_id
    FROM Reservatie r
    JOIN Printer p ON r.Printer_ID = p.Printer_ID
    WHERE DATE(r.PRINT_START) <= ? AND DATE(r.PRINT_END) >= ?
    ORDER BY r.PRINT_START
");
$stmt->execute([$end, $start]);
$reservations = $stmt->fetchAll();

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

<!-- Include FullCalendar -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    
    var printers = <?php echo json_encode($printers); ?>;
    var reservations = <?php echo json_encode($reservations); ?>;
    
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'resourceTimeGridWeek',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'resourceTimeGridDay,resourceTimeGridWeek,dayGridMonth'
        },
        resources: printers.map(function(printer) {
            return {
                id: printer.id,
                title: printer.name,
                status: printer.status
            };
        }),
        events: reservations,
        slotMinTime: '08:00:00',
        slotMaxTime: '20:00:00',
        allDaySlot: false,
        height: 'auto',
        eventClick: function(info) {
            // Display printer info 
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
    });
    
    calendar.render();
});
</script>

<?php include 'includes/footer.php'; ?>