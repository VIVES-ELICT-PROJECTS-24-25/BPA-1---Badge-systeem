/***
 * Fix for reservatie.js - Addresses event declaration issues and missing functions
 */

// Wait for the page to load
document.addEventListener('DOMContentLoaded', function() {
    // Fix 1: Don't redeclare events if it already exists
    // Fix 2: Make goToStep2 function globally available by copying from the event handler
    
    // Check if goToStep2 is undefined and the button exists
    if (typeof goToStep2 === 'undefined') {
        // Define goToStep2 globally
        window.goToStep2 = function() {
            // Simple validation
            const eventName = document.getElementById('eventName').value.trim();
            if (!eventName) {
                alert("Vul alstublieft een naam in.");
                return;
            }
            
            // Hide step 1 and show step 2
            document.getElementById('step1Container').style.display = 'none';
            document.getElementById('step2Container').style.display = 'block';
        };
        
        console.log("✅ goToStep2 function defined globally");
    }
    
    // Update time slots to 8AM-5PM with 30-minute intervals
    // Override populateTimeDropdown
    if (typeof populateTimeDropdown === 'function') {
        const originalPopulateTimeDropdown = populateTimeDropdown;
        
        // Replace the function
        window.populateTimeDropdown = function() {
            const timeSelect = document.getElementById('startTime');
            if (!timeSelect) return;
            
            timeSelect.innerHTML = ''; // Clear existing options
            
            const startHour = 8; // 8 AM
            const endHour = 17; // 5 PM
            
            window.timeslots = [];
            for (let hour = startHour; hour < endHour; hour++) {
                for (let minute = 0; minute < 60; minute += 30) {
                    const value = hour + minute/60; // Store as decimal for calculations
                    const displayHour = hour > 12 ? hour - 12 : hour;
                    const ampm = hour >= 12 ? 'PM' : 'AM';
                    const displayMinute = minute === 0 ? '00' : minute;
                    const displayText = `${displayHour}:${displayMinute} ${ampm}`;
                    
                    window.timeslots.push(value);
                    
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = displayText;
                    timeSelect.appendChild(option);
                }
            }
            
            // Call updateAvailableTimes if it exists
            if (typeof updateAvailableTimes === 'function') {
                updateAvailableTimes();
            }
            
            console.log("✅ Time slots updated to 8AM-5PM with 30-minute intervals");
        };
        
        // Update validateDuration to work with 30-minute intervals
        if (typeof validateDuration === 'function') {
            const originalValidateDuration = validateDuration;
            
            window.validateDuration = function() {
                const durationInput = document.getElementById('printDuration');
                if (!durationInput) return;
                
                const value = parseFloat(durationInput.value);
                
                if (value < 0.5) {
                    durationInput.value = 0.5; // Minimum 30 minutes
                } else {
                    // Round to nearest half hour
                    durationInput.value = Math.round(value * 2) / 2;
                }
                
                if (value > parseFloat(durationInput.max)) {
                    durationInput.value = durationInput.max;
                }
            };
            
            console.log("✅ Duration validation updated for 30-minute intervals");
        }
        
        // Modify createTimeHeaders for 8AM-5PM range
        if (typeof createTimeHeaders === 'function') {
            const originalCreateTimeHeaders = createTimeHeaders;
            
            window.createTimeHeaders = function() {
                const timeRow = document.getElementById('timeHeader');
                if (!timeRow) {
                    console.error("timeHeader element not found");
                    return;
                }
                
                timeRow.innerHTML = ''; // Clear existing
                
                // Add time slots from 8AM to 5PM
                for (let hour = 8; hour <= 17; hour++) {
                    const timeSlot = document.createElement('div');
                    timeSlot.className = 'time-block';
                    timeSlot.textContent = hour <= 12 ? `${hour}AM` : `${hour-12}PM`;
                    timeRow.appendChild(timeSlot);
                }
                
                console.log("✅ Time headers updated for 8AM-5PM range");
            };
        }
        
        // Add CSS for 10 column grid
        const styleSheet = document.createElement('style');
        styleSheet.textContent = `
        .timeline-row {
            display: grid;
            grid-template-columns: repeat(10, 1fr); /* 10 columns for 9 hour slots + buffer */
            height: 60px;
            border-bottom: 1px solid #e5e7eb;
            position: relative;
        }
        `;
        document.head.appendChild(styleSheet);
        
        console.log("✅ CSS grid updated for timeline");
        
        // Update the renderEvent function for 8AM-5PM timeline
        if (typeof renderEvent === 'function') {
            const originalRenderEvent = renderEvent;
            
            window.renderEvent = function(printerId, title, startHour, endHour, color, opo, filamentType, filamentColor, filamentWeight) {
                const printerRow = document.getElementById(`printer${printerId}`);
                
                if (!printerRow) {
                    console.error("Printer niet gevonden:", printerId);
                    return;
                }

                // Create main event container
                let eventDiv = document.createElement("div");
                eventDiv.className = "reservation-block";
                
                // Calculate the total hours displayed (8AM to 5PM = 9 hours)
                const totalHours = 9;
                
                // Calculate position and width as percentages
                const startPercent = ((startHour - 8) / totalHours) * 100;
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
            };
            
            console.log("✅ Event rendering updated for 8AM-5PM timeline");
        }
        
        // Call populateTimeDropdown again to update the dropdown with new time slots
        if (document.getElementById('startTime')) {
            populateTimeDropdown();
        }
    }
});

console.log("🔄 Reservation system fix script loaded");