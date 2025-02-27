let events = [];
let formStep = 1; // Stap 1 = basisgegevens, Stap 2 = extra gegevens

// Update the form structure to support a two-step process
function updateFormStructure() {
    const formContainer = document.querySelector('.form-container');
    
    if (formContainer) {
        // Controleer of de step2 container al bestaat
        if (!document.getElementById("step2Container")) {
            // Maak de tweede stap div aan
            const step2Container = document.createElement("div");
            step2Container.id = "step2Container";
            step2Container.className = "form-step";
            step2Container.style.display = "none"; // Verborgen bij start
            
            // Voeg OPO veld toe
            const opoField = document.createElement("div");
            opoField.className = "input-field";
            
            // Voeg type filament veld toe
            const filamentTypeField = document.createElement("div");
            filamentTypeField.className = "input-field";
            
            // Voeg kleur filament veld toe
            const filamentColorField = document.createElement("div");
            filamentColorField.className = "input-field";

            
            // Voeg hoeveelheid gram filament veld toe
            const filamentWeightField = document.createElement("div");
            filamentWeightField.className = "input-field";
            
            // Voeg deze velden toe aan de stap 2 container
            step2Container.appendChild(opoField);
            step2Container.appendChild(filamentTypeField);
            step2Container.appendChild(filamentColorField);
            step2Container.appendChild(filamentWeightField);
            
            // Voeg de submitknop toe voor stap 2
            const submitBtn = document.createElement("div");
            submitBtn.className = "input-field";
            step2Container.appendChild(submitBtn);
            
            // Voeg "Volgende" knop toe aan einde van stap 1 container
            const step1Container = document.createElement("div");
            step1Container.id = "step1Container";
            step1Container.className = "form-step";
            
            // Verplaats alle bestaande formuliervelden naar stap 1
            const existingFields = Array.from(formContainer.children);
            existingFields.forEach(field => {
                // Verplaats de bestaande velden naar stap 1 container
                step1Container.appendChild(field);
            });
            
            // Voeg de volgende knop toe aan het einde van stap 1
            const nextBtn = document.createElement("div");
            nextBtn.className = "input-field";
            step1Container.appendChild(nextBtn);
            
            // Voeg beide stappen toe aan het formulier
            formContainer.appendChild(step1Container);
            formContainer.appendChild(step2Container);
            
            // Voeg event listeners toe voor de knoppen
            document.getElementById("nextToStep2").addEventListener("click", goToStep2);
            document.getElementById("submitReservation").addEventListener("click", addReservation);
            
            // Werk eventuele select elementen bij als Materialize wordt gebruikt
            if (typeof M !== 'undefined') {
                M.FormSelect.init(document.querySelectorAll('select'));
            }
        }
    }
}

// Functie om naar stap 2 te gaan
function goToStep2() {
    // Valideer eerst de stap 1 gegevens
    const eventName = document.getElementById("eventName").value;
    const selectedDate = document.getElementById("date").value;
    const startTime = document.getElementById("startTime").value;
    const printDuration = document.getElementById("printDuration").value;
    const printer = document.getElementById("printer").value;
    
    // Valideer de velden
    if (!eventName.trim()) {
        alert("Voer je naam in.");
        return;
    }
    
    if (!selectedDate) {
        alert("Kies een datum.");
        return;
    }
    
    if (!startTime) {
        alert("Kies een starttijd.");
        return;
    }
    
    if (!printDuration) {
        alert("Voer een printduur in.");
        return;
    }
    
    if (!printer) {
        alert("Kies een printer.");
        return;
    }
    
    // Als alles correct is, ga naar stap 2
    document.getElementById("step1Container").style.display = "none";
    document.getElementById("step2Container").style.display = "block";
    formStep = 2;
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

// Modified addReservation function to include the new fields from step 2
function addReservation() {
    const printerId = document.getElementById("printer").value;
    const eventName = document.getElementById("eventName").value;
    const selectedDate = document.getElementById("date").value;
    const startTime = parseFloat(document.getElementById("startTime").value);
    const printDuration = parseFloat(document.getElementById("printDuration").value);
    
    // Nieuwe velden ophalen uit stap 2
    const opo = document.getElementById("opoInput").value;
    const filamentType = document.getElementById("filamentType").value;
    const filamentColor = document.getElementById("filamentColor").value;
    const filamentWeight = document.getElementById("filamentWeight").value;
    
    // Validatie voor stap 2 velden
    if (!opo.trim()) {
        alert("Voer OPO in. indien geen OPO, vul 'geen' in.");
        return;
    }
    
    if (!filamentType) {
        alert("Kies type filament.");
        return;
    }
    
    if (!filamentColor.trim()) {
        alert("Voer filament kleur in.");
        return;
    }
    
    if (!filamentWeight || filamentWeight <= 0) {
        alert("Voer geldige hoeveelheid filament in (gram).");
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
        alert("Reservatie uren moeten binnen deze uren blijven (6AM - 6PM).");
        return;
    }

    // Check if selected date is in the past
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const selectedDateTime = new Date(selectedDate);
    if (selectedDateTime < today) {
        alert("Kan geen datum kiezen in het verleden.");
        return;
    }

    // Check for overlapping reservations
    for (let event of events) {
        if (event.printer === printerId &&
            event.date === selectedDate &&
            ((actualStartTime >= event.start && actualStartTime < event.end) ||
            (endTime > event.start && endTime <= event.end) ||
            (actualStartTime <= event.start && endTime >= event.end))) {
            alert("Tijdslot is ingenomen. Kies een andere tijd.");
            return;
        }
    }

    // Add to event list
    let colors = ["blue", "green", "orange", "red"];
    let color = colors[Math.floor(Math.random() * colors.length)];
    
    // Voeg alle velden toe aan events array
    events.push({ 
        printer: printerId, 
        title: eventName, 
        date: selectedDate,
        start: actualStartTime,
        end: endTime,
        color,
        opo: opo,
        filamentType: filamentType,
        filamentColor: filamentColor,
        filamentWeight: filamentWeight
    });

    // Clear previous events and render all events for the selected date
    clearEvents();
    renderEvents(selectedDate);
    
    // Reset formulier en ga terug naar stap 1
    document.getElementById("eventName").value = "";
    document.getElementById("opoInput").value = "";
    document.getElementById("filamentType").selectedIndex = 0;
    document.getElementById("filamentColor").value = "";
    document.getElementById("filamentWeight").value = "";
    
    // Reset formulierstap
    goToStep1();
    
    // Geef bevestiging
    alert("Reservatie toegevoegd!");
    
    // Herbouw select elementen als Materialize wordt gebruikt
    if (typeof M !== 'undefined') {
        M.FormSelect.init(document.querySelectorAll('select'));
    }
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

// Fixed renderEvent function with proper scaling and tooltip data
function renderEvent(printerId, title, startHour, endHour, color, opo, filamentType, filamentColor, filamentWeight) {
    const printerRow = document.querySelector('.timeline-container .timeline #' + printerId);
    
    if (!printerRow) {
        console.error("Printer niet gevonden:", printerId);
        return;
    }

    // Create main event container
    let eventDiv = document.createElement("div");
    eventDiv.className = "event " + color;
    
    // Calculate the total hours displayed (6AM to 6PM = 12 hours)
    const totalHours = 12;
    
    // Calculate position and width as percentages instead of pixels
    const startPercent = ((startHour - 6) / totalHours) * 100;
    const widthPercent = ((endHour - startHour) / totalHours) * 100;
    
    // Apply positioning using percentages
    eventDiv.style.left = startPercent + '%';
    eventDiv.style.width = widthPercent + '%';
    
    // Set primary content (title only)
    eventDiv.textContent = title;
    
    // Add data attributes for tooltip content, inclusief de nieuwe velden
    eventDiv.dataset.startTime = formatTime(startHour);
    eventDiv.dataset.endTime = formatTime(endHour);
    eventDiv.dataset.opo = opo;
    eventDiv.dataset.filamentType = filamentType;
    eventDiv.dataset.filamentColor = filamentColor;
    eventDiv.dataset.filamentWeight = filamentWeight + "g";
    
    // Tooltip tekst samenstellen
    const tooltipText = `
        ${title}
        Tijd: ${formatTime(startHour)} - ${formatTime(endHour)}
        OPO: ${opo}
        Filament: ${filamentType} (${filamentColor})
        Hoeveelheid: ${filamentWeight}g
    `;
    eventDiv.title = tooltipText.trim();
    
    // Add the event div to the printer row
    printerRow.appendChild(eventDiv);
    
    // Initialiseer tooltip als Materialize wordt gebruikt
    if (typeof M !== 'undefined' && M.Tooltip) {
        M.Tooltip.init(eventDiv, {
            html: tooltipText.replace(/\n/g, '<br>')
        });
    }
}

// Improve the resize handler to properly refresh the calendar
window.addEventListener('resize', () => {
    // Get the currently selected date
    const selectedDate = document.getElementById("date").value;
    
    // Only refresh if there's a date selected
    if (selectedDate) {
        clearEvents();
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
    document.addEventListener('DOMContentLoaded', function () {
        const filamentSelect = document.getElementById("filamentType");
        const customFilamentField = document.getElementById("customFilament");
    
        // Verberg standaard het extra invoerveld
        customFilamentField.style.display = "none";
    
        filamentSelect.addEventListener("change", function () {
            if (filamentSelect.value === "Andere") {
                customFilamentField.style.display = "block";
            } else {
                customFilamentField.style.display = "none";
            }
        });
    });
    
    generateTimeOptions();
    updateTimeSlots();
});