document.addEventListener('DOMContentLoaded', function() {
    // Get the clock and date elements
    const clockElement = document.getElementById('clock');
    const dateElement = document.getElementById('date');
    
    // Function to update the clock
    function updateClock() {
        const now = new Date();
        
        // Format the time (HH:MM:SS)
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const timeString = `${hours}:${minutes}:${seconds}`;
        
        // Format the date (DD/MM/YYYY)
        const day = String(now.getDate()).padStart(2, '0');
        const month = String(now.getMonth() + 1).padStart(2, '0'); // Months are zero-based
        const year = now.getFullYear();
        const dateString = `${day}/${month}/${year}`;
        
        // Update the elements if they exist
        if (clockElement) clockElement.textContent = timeString;
        if (dateElement) dateElement.textContent = dateString;
    }
    
    // Update the clock immediately and then every second
    updateClock();
    setInterval(updateClock, 1000);
});