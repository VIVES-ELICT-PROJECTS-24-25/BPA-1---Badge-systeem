let events = [];

// Function to generate time options
function generateTimeOptions() {
    const startHour = 6; // 6 AM
    const endHour = 18; // 6 PM
    const startSelect = document.getElementById("start");
    const endSelect = document.getElementById("end");
    
    // Clear existing options
    startSelect.innerHTML = '';
    endSelect.innerHTML = '';
    
    // Generate options for each half hour
    for (let hour = startHour; hour <= endHour; hour++) {
        for (let minute = 0; minute < 60; minute += 30) {
            const value = hour + minute/60; // Store as decimal for calculations
            const displayHour = hour > 12 ? hour - 12 : hour;
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const displayMinute = minute === 0 ? '00' : minute;
            const displayText = `${displayHour}:${displayMinute} ${ampm}`;
            
            // Add to start time dropdown (excluding the last time slot)
            if (value < endHour) {
                const startOption = new Option(displayText, value);
                startSelect.add(startOption);
            }
            
            // Add to end time dropdown (excluding the first time slot)
            if (value > startHour) {
                const endOption = new Option(displayText, value);
                endSelect.add(endOption);
            }
        }
    }
}

function addReservation() {
    let printerId = document.getElementById("printer").value;
    let eventName = document.getElementById("eventName").value;
    let selectedDate = document.getElementById("date").value;
    let startHour = parseFloat(document.getElementById("start").value);
    let endHour = parseFloat(document.getElementById("end").value);
    let colors = ["blue", "green", "orange", "red"];
    let color = colors[Math.floor(Math.random() * colors.length)];

    if (!eventName.trim()) {
        alert("Please enter a job name.");
        return;
    }

    if (!selectedDate) {
        alert("Please select a date.");
        return;
    }

    if (endHour <= startHour) {
        alert("End time must be after start time.");
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
            ((startHour >= event.start && startHour < event.end) ||
            (endHour > event.start && endHour <= event.end) ||
            (startHour <= event.start && endHour >= event.end))) {
            alert("Time slot is already booked.");
            return;
        }
    }

    // Add to event list
    events.push({ 
        printer: printerId, 
        title: eventName, 
        date: selectedDate,
        start: startHour, 
        end: endHour, 
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

function renderEvent(printerId, title, startHour, endHour, color) {
    const printerRow = document.querySelector('.timeline-container .timeline #' + printerId);
    
    if (!printerRow) {
        console.error("Printer row not found:", printerId);
        return;
    }

    let eventDiv = document.createElement("div");
    eventDiv.className = "event " + color;
    
    // Get the timeline row width
    const timelineWidth = printerRow.offsetWidth;
    // Calculate the width of one hour slot (total width / number of slots)
    const hourWidth = timelineWidth / 10; // 10 time slots

    // Calculate start position and width
    const startPosition = (startHour - 6) * hourWidth; // 6 is the start hour
    const width = (endHour - startHour) * hourWidth;

    // Set the positioning styles using pixels for exact matching
    eventDiv.style.left = startPosition + 'px';
    eventDiv.style.width = width + 'px';
    eventDiv.textContent = title;

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

// Function to handle date changes
function onDateChange() {
    const selectedDate = document.getElementById("date").value;
    clearEvents();
    renderEvents(selectedDate);
}

// Call this when the page loads
document.addEventListener('DOMContentLoaded', generateTimeOptions);