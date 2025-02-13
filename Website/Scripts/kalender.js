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
    const printerRow = document.getElementById(printerId);
    const totalSlots = 10;
    const slotWidth = 100 / totalSlots;

    let eventDiv = document.createElement("div");
    eventDiv.className = "event " + color;
    eventDiv.style.left = (slotWidth * (startHour - 6)) + "%";
    eventDiv.style.width = (slotWidth * (endHour - startHour)) + "%";
    eventDiv.textContent = title;

    printerRow.appendChild(eventDiv);
}
