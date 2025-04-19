document.addEventListener('DOMContentLoaded', function() {
    // Enable Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Enable Bootstrap popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Auto-dismiss alerts
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert.alert-dismissible');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Forms with validation
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Initialize calendar if it exists on the page
    var calendarEl = document.getElementById('calendar');
    if (calendarEl) {
        initializeCalendar(calendarEl);
    }

    // Initialize charts if they exist on the page
    initializeCharts();
});

// Calendar initialization function
function initializeCalendar(calendarEl) {
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: {
            url: 'api/reservations.php?route=calendar',
            method: 'GET',
            failure: function() {
                alert('Er is een fout opgetreden bij het laden van de reserveringen.');
            }
        },
        eventClick: function(info) {
            console.log(info);
            showEventDetails(info.event);
        },
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        },
        firstDay: 1, // Monday as first day
        locale: 'nl',
        height: 'auto',
        // Add resource view if needed for multiple printers
        views: {
            resourceTimelineWeek: {
                type: 'resourceTimeline',
                duration: { weeks: 1 }
            }
        }
    });
    
    calendar.render();
}

// Function to show event details in a modal
function showEventDetails(event) {
    var title = event.title;
    var start = event.start ? formatDateTime(event.start) : 'N/A';
    var end = event.end ? formatDateTime(event.end) : 'N/A';
    
    var modalHtml = `
        <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="eventModalLabel">Reservering Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p><strong>Printer:</strong> ${title}</p>
                        <p><strong>Start tijd:</strong> ${start}</p>
                        <p><strong>Eind tijd:</strong> ${end}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Sluiten</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    var existingModal = document.getElementById('eventModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add the modal to the document
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show the modal
    var modal = new bootstrap.Modal(document.getElementById('eventModal'));
    modal.show();
}

// Format date and time for display
function formatDateTime(date) {
    var options = { 
        year: 'numeric', 
        month: '2-digit', 
        day: '2-digit', 
        hour: '2-digit', 
        minute: '2-digit', 
        hour12: false 
    };
    return date.toLocaleDateString('nl-NL', options).replace(',', ' ');
}

// Initialize charts for admin dashboard
function initializeCharts() {
    // Popular printers chart
    var printerChartEl = document.getElementById('popularPrintersChart');
    if (printerChartEl) {
        fetch('api/stats.php?route=popular-printers')
            .then(response => response.json())
            .then(data => {
                var printerNames = data.map(item => item.name);
                var reservationCounts = data.map(item => item.reservation_count);
                
                new Chart(printerChartEl, {
                    type: 'bar',
                    data: {
                        labels: printerNames,
                        datasets: [{
                            label: 'Aantal reserveringen',
                            data: reservationCounts,
                            backgroundColor: 'rgba(54, 162, 235, 0.8)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        plugins: {
                            title: {
                                display: true,
                                text: 'Populairste Printers'
                            },
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Aantal reserveringen'
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => console.error('Error loading printer statistics:', error));
    }
    
    // Color usage chart
    var colorChartEl = document.getElementById('colorUsageChart');
    if (colorChartEl) {
        fetch('api/stats.php?route=color-usage')
            .then(response => response.json())
            .then(data => {
                var printTypes = data.map(item => item.print_type);
                var counts = data.map(item => item.count);
                
                new Chart(colorChartEl, {
                    type: 'pie',
                    data: {
                        labels: printTypes,
                        datasets: [{
                            data: counts,
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.8)',
                                'rgba(75, 192, 192, 0.8)'
                            ],
                            borderColor: [
                                'rgba(255, 99, 132, 1)',
                                'rgba(75, 192, 192, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        plugins: {
                            title: {
                                display: true,
                                text: 'Kleurgebruik'
                            }
                        }
                    }
                });
            })
            .catch(error => console.error('Error loading color usage statistics:', error));
    }
    
    // Department usage chart
    var deptChartEl = document.getElementById('departmentUsageChart');
    if (deptChartEl) {
        fetch('api/stats.php?route=department-usage')
            .then(response => response.json())
            .then(data => {
                var departments = data.map(item => item.department);
                var counts = data.map(item => item.reservation_count);
                
                new Chart(deptChartEl, {
                    type: 'bar',
                    data: {
                        labels: departments,
                        datasets: [{
                            label: 'Aantal reserveringen',
                            data: counts,
                            backgroundColor: 'rgba(153, 102, 255, 0.8)',
                            borderColor: 'rgba(153, 102, 255, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        plugins: {
                            title: {
                                display: true,
                                text: 'Gebruik per Richting'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Aantal reserveringen'
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => console.error('Error loading department statistics:', error));
    }
}

// Reservation form handling
function handleReservationForm() {
    var reservationForm = document.getElementById('reservationForm');
    if (!reservationForm) return;
    
    // Handle date-time validation
    var startInput = document.getElementById('start_time');
    var endInput = document.getElementById('end_time');
    
    if (startInput && endInput) {
        startInput.addEventListener('change', function() {
            var startDate = new Date(this.value);
            var endDate = new Date(endInput.value);
            
            if (endDate <= startDate) {
                var newEndDate = new Date(startDate);
                newEndDate.setHours(startDate.getHours() + 1);
                
                endInput.value = formatDateTimeForInput(newEndDate);
            }
            
            // Set min value for end time
            var minEndDate = new Date(startDate);
            minEndDate.setMinutes(startDate.getMinutes() + 30);
            endInput.min = formatDateTimeForInput(minEndDate);
        });
    }
    
    // Handle form submission
    reservationForm.addEventListener('submit', function(event) {
        event.preventDefault();
        
        var formData = {
            printer_id: document.getElementById('printer_id').value,
            start_time: document.getElementById('start_time').value,
            end_time: document.getElementById('end_time').value,
            purpose: document.getElementById('purpose').value,
            color_printing: document.getElementById('color_printing').checked ? 1 : 0,
            notes: document.getElementById('notes').value
        };
        
        fetch('api/reservations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                showAlert(data.error, 'danger');
            } else {
                showAlert('Reservering succesvol aangemaakt!', 'success');
                reservationForm.reset();
                
                // Redirect after delay
                setTimeout(function() {
                    window.location.href = 'my-reservations.php';
                }, 1500);
            }
        })
        .catch(error => {
            console.error('Error creating reservation:', error);
            showAlert('Er is een fout opgetreden bij het maken van de reservering.', 'danger');
        });
    });
}

// Format date for input fields
function formatDateTimeForInput(date) {
    return date.toISOString().slice(0, 16);
}

// Show alert message
function showAlert(message, type = 'info') {
    var alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    var alertContainer = document.querySelector('.alert-container');
    if (!alertContainer) {
        alertContainer = document.createElement('div');
        alertContainer.className = 'alert-container';
        document.querySelector('.container').prepend(alertContainer);
    }
    
    alertContainer.innerHTML = alertHtml;
    
    // Auto dismiss
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert.alert-dismissible');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
}