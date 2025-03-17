let events = [];

function addReservation() {
    let printerId = document.getElementById("printer").value;
    let eventName = document.getElementById("eventName").value;
    let startHour = parseInt(document.getElementById("start").value);
    let endHour = parseInt(document.getElementById("end").value);
    let colors = ["blue", "green", "orange", "red"];
    let color = colors[Math.floor(Math.random() * colors.length)];

    if (!eventName.trim()) {
        alert("Please enter a job name.");
        return;
    }

    if (endHour <= startHour) {
        alert("End time must be after start time.");
        return;
    }

    // Check for overlapping reservations
    for (let event of events) {
        if (event.printer === printerId &&
            ((startHour >= event.start && startHour < event.end) ||
            (endHour > event.start && endHour <= event.end) ||
            (startHour <= event.start && endHour >= event.end))) {
            alert("Time slot is already booked.");
            return;
        }
    }

    // Add to event list
    events.push({ printer: printerId, title: eventName, start: startHour, end: endHour, color });

    // Render event
    renderEvent(printerId, eventName, startHour, endHour, color);
}

function renderEvent(printerId, title, startHour, endHour, color) {
    // Find the printer row within the timeline container
    const printerRow = document.querySelector('.timeline-container .timeline #' + printerId);
   
    if (!printerRow) {
        console.error("Printer row not found:", printerId);
        return;
    }
 
    const totalSlots = 10; // Number of time slots (6AM to 3PM)
    const slotWidth = 100 / totalSlots; // Width per time slot in percentage
 
    let eventDiv = document.createElement("div");
    eventDiv.className = "event " + color;
   
    // Calculate position relative to 6AM (first slot)
    const startPosition = (startHour - 6) * slotWidth;
    const duration = (endHour - startHour) * slotWidth;
 
    // Set the positioning styles
    eventDiv.style.left = startPosition + "%";
    eventDiv.style.width = duration + "%";
    eventDiv.textContent = title;
 
    // Set position to relative on the printer row to establish positioning context
    printerRow.style.position = 'relative';
 
    // Add the event div to the printer row
    printerRow.appendChild(eventDiv);
}