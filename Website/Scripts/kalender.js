let events = [];

// Update the form in the HTML first
function updateFormStructure() {
    const formContainer = document.querySelector('.form-container');
}

// Make sure this function is working correctly
function formatTime(decimalHours) {
    const hours = Math.floor(decimalHours);
    const minutes = Math.round((decimalHours - hours) * 60);
    const ampm = hours >= 12 ? 'PM' : 'AM';
    const displayHour = hours === 0 ? 12 : (hours > 12 ? hours - 12 : hours);
    return `${displayHour}:${minutes.toString().padStart(2, '0')} ${ampm}`;
}

// Generate time options for start time only
function generateTimeOptions() {
    const startHour = 6; // 6 AM
    const endHour = 18; // 6 PM
    const startSelect = document.getElementById("startTime");
    
    startSelect.innerHTML = '';
    
    for (let hour = startHour; hour < endHour; hour++) {
        for (let minute = 0; minute < 60; minute += 15) {
            const value = hour + minute/60; // Store as decimal for calculations
            const displayHour = hour > 12 ? hour - 12 : hour;
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const displayMinute = minute === 0 ? '00' : minute;
            const displayText = `${displayHour}:${displayMinute} ${ampm}`;
            
            const option = new Option(displayText, value);
            startSelect.add(option);
        }
    }
}

// Modified addReservation function
function addReservation() {
    const printerId = document.getElementById("printer").value;
    const eventName = document.getElementById("eventName").value;
    const selectedDate = document.getElementById("date").value;
    const startTime = parseFloat(document.getElementById("startTime").value);
    const printDuration = parseFloat(document.getElementById("printDuration").value);
    
    if (!eventName.trim()) {
        alert("Please enter a job name.");
        return;
    }

    if (!selectedDate) {
        alert("Please select a date.");
        return;
    }

    // Calculate total time including setup and cooldown
    const setupDuration = 0.25; // 15 minutes
    const cooldownDuration = printDuration * 0.1;
    
    // Special handling for 6:00 AM start time
    let actualStartTime, endTime;
    
    if (startTime === 6) {
        // For 6:00 AM, we start exactly at 6:00 (no setup time before)
        actualStartTime = 6;
        // Add setup time to the total duration instead
        endTime = startTime + setupDuration + printDuration + cooldownDuration;
    } else {
        // For other times, apply the setup time before the start time
        actualStartTime = startTime - setupDuration;
        endTime = startTime + printDuration + cooldownDuration;
    }

    // Check if total time exceeds operating hours
    if (endTime > 18) {
        alert("Total reservation time must be within operating hours (6AM - 6PM).");
        return;
    }

    // Check if selected date is in the past
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const selectedDateTime = new Date(selectedDate);
    if (selectedDateTime < today) {
        alert("Cannot make reservations for past dates.");
        return;
    }

    // Check for overlapping reservations
    for (let event of events) {
        if (event.printer === printerId &&
            event.date === selectedDate &&
            ((actualStartTime >= event.start && actualStartTime < event.end) ||
            (endTime > event.start && endTime <= event.end) ||
            (actualStartTime <= event.start && endTime >= event.end))) {
            alert("Time slot is already booked.");
            return;
        }
    }

    // Add to event list
    let colors = ["blue", "green", "orange", "red"];
    let color = colors[Math.floor(Math.random() * colors.length)];
    
    events.push({ 
        printer: printerId, 
        title: eventName, 
        date: selectedDate,
        start: actualStartTime,
        end: endTime,
        color 
    });

    // Clear previous events and render all events for the selected date
    clearEvents();
    renderEvents(selectedDate);
}

function clearEvents() {
    const printerRows = document.querySelectorAll('.timeline-container .timeline .timeline-row');
    printerRows.forEach(row => {
        const events = row.querySelectorAll('.event');
        events.forEach(event => event.remove());
    });
}

function renderEvents(date) {
    // Filter events for the selected date
    const dateEvents = events.filter(event => event.date === date);
    
    // Render each event
    dateEvents.forEach(event => {
        renderEvent(event.printer, event.title, event.start, event.end, event.color);
    });
}

// Modify just the renderEvent function in your kalender.js file
function renderEvent(printerId, title, startHour, endHour, color) {
    const printerRow = document.querySelector('.timeline-container .timeline #' + printerId);
    
    if (!printerRow) {
        console.error("Printer row not found:", printerId);
        return;
    }

    // Create main event container
    let eventDiv = document.createElement("div");
    eventDiv.className = "event " + color;
    
    // Get the timeline row width
    const timelineWidth = printerRow.offsetWidth;
    const hourWidth = timelineWidth / 13; // 13 time slots (6AM to 6PM)

    // Calculate start position and width
    const startPosition = (startHour - 6) * hourWidth; // 6 is the start hour
    const width = (endHour - startHour) * hourWidth;

    // Set the positioning styles
    eventDiv.style.left = startPosition + 'px';
    eventDiv.style.width = width + 'px';
    
    // Set primary content (title only)
    eventDiv.textContent = title;
    
    // Add data attributes for the tooltip content
    eventDiv.dataset.startTime = formatTime(startHour);
    eventDiv.dataset.endTime = formatTime(endHour);
    
    // Add the event div to the printer row
    printerRow.appendChild(eventDiv);
}
// Add window resize handler to update event positions
window.addEventListener('resize', () => {
    clearEvents();
    const selectedDate = document.getElementById("date").value;
    if (selectedDate) {
        renderEvents(selectedDate);
    }
});

// Update the time slots in the HTML
function updateTimeSlots() {
    const timelineRow = document.querySelector('.timeline-row:first-child');
    timelineRow.innerHTML = ''; // Clear existing slots
    
    // Add all time slots from 6AM to 6PM
    for (let hour = 6; hour <= 18; hour++) {
        const timeSlot = document.createElement('div');
        timeSlot.className = 'time-slot';
        timeSlot.textContent = hour <= 12 ? `${hour}AM` : `${hour-12}PM`;
        timelineRow.appendChild(timeSlot);
    }
}

// Initialize everything when the page loads
document.addEventListener('DOMContentLoaded', () => {
    updateFormStructure();
    generateTimeOptions();
    updateTimeSlots();
});