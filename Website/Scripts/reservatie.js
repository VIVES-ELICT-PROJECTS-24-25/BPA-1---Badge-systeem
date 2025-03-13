/**
 * Reservation System JavaScript
 * Handles form interactions, timeline display, and API calls
 */

// Global variables
let selectedDate = new Date();
let availablePrinters = [];
let timeslots = [];
let reservations = {};
let events = []; // From your existing code

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    // Set today's date as default
    const dateInput = document.getElementById('date');
    selectedDate = new Date(dateInput.value);
    
    // Initialize the form
    initializeForm();
    
    // Load printers and reservations
    loadPrinters();
    
    // Event listeners for form navigation
    document.getElementById('nextToStep2').addEventListener('click', goToStep2);
    document.getElementById('backToStep1').addEventListener('click', () => {
        document.getElementById('step2Container').style.display = 'none';
        document.getElementById('step1Container').style.display = 'block';
    });
    document.getElementById('submitReservation').addEventListener('click', submitReservation);
    
    // Event listener for filament type and color custom options
    document.getElementById('filamentType').addEventListener('change', handleCustomFilamentType);
    document.getElementById('filamentColor').addEventListener('change', handleCustomFilamentColor);
    
    // Hide custom input fields initially
    document.getElementById('customFilament').style.display = 'none';
    document.getElementById('customColor').style.display = 'none';
    
    // Initialize the time slots in the timeline
    updateTimeSlots();
});

// Load printers from PHP (passed via initialPrinters variable)
async function loadPrinters() {
    availablePrinters = initialPrinters;
    
    // After loading printers, load their reservations
    await loadAllPrinterReservations();
    
    // Initialize timeline
    updateTimeline();
}

// Load reservations for all available printers
async function loadAllPrinterReservations() {
    reservations = {};
    
    for (const printer of availablePrinters) {
        try {
            const response = await fetch(`reservatie_api.php?printer_id=${printer.Printer_ID}`);
            const data = await response.json();
            
            if (data.status === 'success') {
                reservations[printer.Printer_ID] = data.data;
                
                // Convert to events format for compatibility with existing code
                data.data.forEach(reservation => {
                    const startDateTime = new Date(reservation.Pr_Start);
                    const endDateTime = new Date(reservation.Pr_End);
                    
                    events.push({
                        printer: printer.Printer_ID,
                        title: `${reservation.Voornaam || ''} ${reservation.Naam || ''}`,
                        date: startDateTime.toISOString().split('T')[0],
                        start: startDateTime.getHours() + startDateTime.getMinutes()/60,
                        end: endDateTime.getHours() + endDateTime.getMinutes()/60,
                        color: 'green',
                        opo: reservation.Comment || 'Geen project info',
                        filamentType: reservation.Filament_Type || 'Onbekend',
                        filamentColor: reservation.Filament_Kleur || 'Onbekend',
                        filamentWeight: 'Onbekend' // Not directly available in API data
                    });
                });
            } else {
                console.error('Error fetching reservations:', data.message);
                reservations[printer.Printer_ID] = [];
            }
        } catch (error) {
            console.error('API Error:', error);
            reservations[printer.Printer_ID] = [];
        }
    }
}

// Initialize form elements
function initializeForm() {
    populateTimeDropdown();
    
    // Set up change listeners
    document.getElementById('date').addEventListener('change', onDateChange);
    document.getElementById('printer').addEventListener('change', updateAvailableTimes);
    document.getElementById('startTime').addEventListener('change', updateAvailableDurations);
    document.getElementById('printDuration').addEventListener('change', validateDuration);
}

// Populate the time dropdown with 30-minute intervals
function populateTimeDropdown() {
    const timeSelect = document.getElementById('startTime');
    timeSelect.innerHTML = ''; // Clear existing options
    
    const startHour = 6; // 6 AM
    const endHour = 18; // 6 PM
    
    timeslots = [];
    for (let hour = startHour; hour < endHour; hour++) {
        for (let minute = 0; minute < 60; minute += 15) {
            const value = hour + minute/60; // Store as decimal for calculations
            const displayHour = hour > 12 ? hour - 12 : hour;
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const displayMinute = minute === 0 ? '00' : minute;
            const displayText = `${displayHour}:${displayMinute} ${ampm}`;
            
            timeslots.push(value);
            
            const option = document.createElement('option');
            option.value = value;
            option.textContent = displayText;
            timeSelect.appendChild(option);
        }
    }
    updateAvailableTimes();
}

// Format time for display
function formatTime(decimalHours) {
    const hours = Math.floor(decimalHours);
    const minutes = Math.round((decimalHours - hours) * 60);
    const ampm = hours >= 12 ? 'PM' : 'AM';
    const displayHour = hours === 0 ? 12 : (hours > 12 ? hours - 12 : hours);
    return `${displayHour}:${minutes.toString().padStart(2, '0')} ${ampm}`;
}

// Update the timeline
function updateTimeline() {
    clearTimeline();
    createTimeHeaders();
    
    // Get the selected date as a string
    const dateStr = selectedDate.toISOString().split('T')[0];
    
    // Using your renderEvents approach
    renderEvents(dateStr);
}

// Clear the timeline
function clearTimeline() {
    const timelineRows = document.querySelectorAll('.timeline-row');
    timelineRows.forEach(row => {
        // Preserve the first row (time headers)
        if (row.id !== 'timeHeader') {
            row.innerHTML = '';
        }
    });
}

// Create time headers (6:00 AM - 6:00 PM)
function createTimeHeaders() {
    const timeRow = document.getElementById('timeHeader');
    timeRow.innerHTML = ''; // Clear existing
    
    // Add time slots from 6AM to 6PM
    for (let hour = 6; hour <= 18; hour++) {
        const timeSlot = document.createElement('div');
        timeSlot.className = 'time-block';
        timeSlot.textContent = hour <= 12 ? `${hour}AM` : `${hour-12}PM`;
        timeRow.appendChild(timeSlot);
    }
}
// Render events for a specific date
function renderEvents(date) {
    // Filter events for the selected date
    const dateEvents = events.filter(event => event.date === date);
    
    // Render each event
    dateEvents.forEach(event => {
        renderEvent(
            event.printer, 
            event.title, 
            event.start, 
            event.end, 
            event.color, 
            event.opo,
            event.filamentType,
            event.filamentColor,
            event.filamentWeight
        );
    });
}

// Render a single event on the timeline
function renderEvent(printerId, title, startHour, endHour, color, opo, filamentType, filamentColor, filamentWeight) {
    const printerRow = document.getElementById(`printer${printerId}`);
    
    if (!printerRow) {
        console.error("Printer niet gevonden:", printerId);
        return;
    }

    // Create main event container
    let eventDiv = document.createElement("div");
    eventDiv.className = "reservation-block";
    
    // Calculate the total hours displayed (6AM to 6PM = 12 hours)
    const totalHours = 12;
    
    // Calculate position and width as percentages
    const startPercent = ((startHour - 6) / totalHours) * 100;
    const widthPercent = ((endHour - startHour) / totalHours) * 100;
    
    // Apply positioning using percentages
    eventDiv.style.left = startPercent + '%';
    eventDiv.style.width = widthPercent + '%';
    
    // Set primary content (title only)
    eventDiv.textContent = `${formatTime(startHour)} - ${formatTime(endHour)}`;
    
    // Create tooltip text
    const tooltipText = `
        ${title}
        Tijd: ${formatTime(startHour)} - ${formatTime(endHour)}
        OPO: ${opo}
        Filament: ${filamentType} (${filamentColor})
        Hoeveelheid: ${filamentWeight}
    `;
    eventDiv.title = tooltipText.trim();
    
    // Add the event div to the printer row
    printerRow.appendChild(eventDiv);
}

// Update time slots in the timeline
function updateTimeSlots() {
    createTimeHeaders();
}

// Handle date change
function onDateChange() {
    const dateInput = document.getElementById('date');
    selectedDate = new Date(dateInput.value);
    
    updateAvailableTimes();
    updateTimeline();
}

// Update available times based on existing reservations
function updateAvailableTimes() {
    const timeSelect = document.getElementById('startTime');
    const printerId = document.getElementById('printer').value;
    const dateStr = document.getElementById('date').value;
    
    // Disable times that are already reserved or in the past
    Array.from(timeSelect.options).forEach(option => {
        const timeValue = parseFloat(option.value);
        const hour = Math.floor(timeValue);
        const minute = Math.round((timeValue - hour) * 60);
        
        const dateTime = new Date(dateStr);
        dateTime.setHours(hour, minute, 0, 0);
        
        // Check if this time is in the past
        const isPast = dateTime < new Date();
        
        // Check if this time overlaps with existing reservations
        let isReserved = false;
        const relevantEvents = events.filter(event => 
            event.printer == printerId && 
            event.date === dateStr
        );
        
        isReserved = relevantEvents.some(event => 
            (timeValue >= event.start && timeValue < event.end)
        );
        
        option.disabled = isPast || isReserved;
    });
    
    // Select the first available time
    for (let i = 0; i < timeSelect.options.length; i++) {
        if (!timeSelect.options[i].disabled) {
            timeSelect.selectedIndex = i;
            break;
        }
    }
    
    updateAvailableDurations();
}

// Update available durations based on start time and other reservations
function updateAvailableDurations() {
    const printerId = document.getElementById('printer').value;
    const dateStr = document.getElementById('date').value;
    const startTimeValue = parseFloat(document.getElementById('startTime').value);
    const durationInput = document.getElementById('printDuration');
    
    // Calculate max duration based on next reservation for the same printer
    const relevantEvents = events.filter(event => 
        event.printer == printerId && 
        event.date === dateStr &&
        event.start > startTimeValue
    ).sort((a, b) => a.start - b.start);
    
    // Calculate max duration (in hours)
    let maxDuration = 12; // Default max
    
    if (relevantEvents.length > 0) {
        // Get the next reservation
        const nextEvent = relevantEvents[0];
        maxDuration = nextEvent.start - startTimeValue;
    }
    
    // Ensure max duration doesn't go past 5:00 PM (17:00)
    const endTimeLimit = 17; // 5 PM
    const maxPossibleDuration = endTimeLimit - startTimeValue;
    maxDuration = Math.min(maxDuration, maxPossibleDuration);
    
    // Account for setup and cooldown time
    const setupDuration = 0.25; // 15 minutes
    const cooldownFactor = 0.1; // 10% of print time for cooldown
    
    const maxPrintDuration = (maxDuration - setupDuration) / (1 + cooldownFactor);
    
    // Round down to nearest 0.25
    const roundedMaxDuration = Math.floor(maxPrintDuration * 4) / 4;
    
    // Update the duration input
    durationInput.max = roundedMaxDuration;
    
    // If current value exceeds max, adjust it
    if (parseFloat(durationInput.value) > durationInput.max) {
        durationInput.value = durationInput.max;
    }
}

// Validate the duration input
function validateDuration() {
    const durationInput = document.getElementById('printDuration');
    const value = parseFloat(durationInput.value);
    
    if (value < 0.25) {
        durationInput.value = 0.25;
    } else if (value > parseFloat(durationInput.max)) {
        durationInput.value = durationInput.max;
    }
}

// Move to step 2 of the form
function goToStep2() {
    // Simple validation
    const eventName = document.getElementById('eventName').value.trim();
    if (!eventName) {
        showMessage("Vul alstublieft een naam in.", "error");
        return;
    }
    
    // Hide step 1 and show step 2
    document.getElementById('step1Container').style.display = 'none';
    document.getElementById('step2Container').style.display = 'block';
}

// Go back to step 1
function goToStep1() {
    document.getElementById('step2Container').style.display = 'none';
    document.getElementById('step1Container').style.display = 'block';
}

// Handle custom filament type selection
function handleCustomFilamentType() {
    const select = document.getElementById('filamentType');
    const customInput = document.getElementById('customFilament');
    
    if (select.value === 'Andere') {
        customInput.style.display = 'block';
        customInput.required = true;
    } else {
        customInput.style.display = 'none';
        customInput.required = false;
    }
}

// Handle custom color selection
function handleCustomFilamentColor() {
    const select = document.getElementById('filamentColor');
    const customInput = document.getElementById('customColor');
    
    if (select.value === 'Andere') {
        customInput.style.display = 'block';
        customInput.required = true;
    } else {
        customInput.style.display = 'none';
        customInput.required = false;
    }
}

// Submit the reservation to the API
async function submitReservation() {
    // Gather data from the form
    const userId = currentUser.userId;
    const printerId = document.getElementById('printer').value;
    const dateStr = document.getElementById('date').value;
    const startTimeVal = parseFloat(document.getElementById('startTime').value);
    const durationHours = parseFloat(document.getElementById('printDuration').value);
    const opoProject = document.getElementById('opoInput').value.trim();
    
    // Get filament type (check if custom)
    let filamentType = document.getElementById('filamentType').value;
    if (filamentType === 'Andere') {
        filamentType = document.getElementById('customFilament').value.trim();
        if (!filamentType) {
            showMessage("Vul het type filament in.", "error");
            return;
        }
    }
    
    // Get filament color (check if custom)
    let filamentColor = document.getElementById('filamentColor').value;
    if (filamentColor === 'Andere') {
        filamentColor = document.getElementById('customColor').value.trim();
        if (!filamentColor) {
            showMessage("Vul de kleur van het filament in.", "error");
            return;
        }
    }
    
    // Get filament weight
    const filamentWeight = document.getElementById('filamentWeight').value;
    if (!filamentWeight || parseFloat(filamentWeight) <= 0) {
        showMessage("Voer een geldige hoeveelheid filament in.", "error");
        return;
    }
    
    // Calculate actual start and end times
    let actualStartTime, endTime;
    
    // Special handling for 6:00 AM start time
    if (startTimeVal === 6) {
        // For 6:00 AM, we start exactly at 6:00 (no setup time before)
        actualStartTime = startTimeVal;
        // Add setup time to the total duration instead
        endTime = startTimeVal + setupDuration + durationHours + cooldownDuration;
    } else {
        // For other times, apply the setup time before the start time
        actualStartTime = startTimeVal; // Keep UI time as start time
        endTime = startTimeVal + setupDuration + durationHours + cooldownDuration;
    }
    
    // Convert decimal hours to Date objects for the API
    const startDate = new Date(dateStr);
    const startHour = Math.floor(actualStartTime);
    const startMinute = Math.round((actualStartTime - startHour) * 60);
    startDate.setHours(startHour, startMinute, 0, 0);
    
    const endDate = new Date(dateStr);
    const endHour = Math.floor(endTime);
    const endMinute = Math.round((endTime - endHour) * 60);
    endDate.setHours(endHour, endMinute, 0, 0);
    
    // Format for MySQL datetime (YYYY-MM-DD HH:MM:SS)
    const startMySql = formatDateTimeForMySQL(startDate);
    const endMySql = formatDateTimeForMySQL(endDate);
    
    // Prepare comment with project and filament info
    const comment = `Project: ${opoProject || 'Niet gespecificeerd'}. Filament: ${filamentWeight || 'Onbekend'}g ${filamentColor} ${filamentType}.`;
    
    // Create reservation data object
    const reservationData = {
        User_ID: userId,
        Printer_ID: printerId,
        Pr_Start: startMySql,
        Pr_End: endMySql,
        Comment: comment,
        Filament_Kleur: filamentColor,
        Filament_Type: filamentType
    };
    
    try {
        // Send the data to the API
        const response = await fetch('reservatie_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(reservationData)
        });
        
        const result = await response.json();
        
        if (result.status === 'success' || result.status === 201) {
            // Show success message with PIN
            showMessage(`Reservering succesvol aangemaakt! Je PIN-code is: ${result.data.Pin}`, "success");
            
            // Add to local events array for immediate UI update
            events.push({
                printer: printerId,
                title: document.getElementById('eventName').value.trim(),
                date: dateStr,
                start: actualStartTime,
                end: endTime,
                color: 'green',
                opo: opoProject,
                filamentType: filamentType,
                filamentColor: filamentColor,
                filamentWeight: filamentWeight
            });
            
            // Reset form and reload data
            document.getElementById('step2Container').style.display = 'none';
            document.getElementById('step1Container').style.display = 'block';
            document.getElementById('opoInput').value = '';
            document.getElementById('filamentType').selectedIndex = 0;
            document.getElementById('filamentColor').selectedIndex = 0;
            document.getElementById('filamentWeight').value = '';
            
            // Reload reservations and update the timeline
            await loadAllPrinterReservations();
            updateTimeline();
        } else {
            showMessage(`Fout bij het aanmaken van de reservering: ${result.message}`, "error");
        }
    } catch (error) {
        console.error('Error:', error);
        showMessage('Er is een fout opgetreden bij het communiceren met de server.', "error");
    }
}

// Format date for MySQL (YYYY-MM-DD HH:MM:SS)
function formatDateTimeForMySQL(date) {
    const year = date.getFullYear();
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const day = date.getDate().toString().padStart(2, '0');
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');
    const seconds = date.getSeconds().toString().padStart(2, '0');
    
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}

// Show message to the user
function showMessage(message, type = "info") {
    const messageContainer = document.getElementById('messageContainer');
    
    const messageElement = document.createElement('div');
    messageElement.textContent = message;
    messageElement.className = type === "error" ? "error-message" : "success-message";
    
    messageContainer.innerHTML = '';
    messageContainer.appendChild(messageElement);
    
    // Auto-hide success messages after 5 seconds
    if (type === "success") {
        setTimeout(() => {
            if (messageContainer.contains(messageElement)) {
                messageContainer.removeChild(messageElement);
            }
        }, 5000);
    }
}

// Handle window resize to refresh the timeline
window.addEventListener('resize', () => {
    if (selectedDate) {
        updateTimeline();
    }
});