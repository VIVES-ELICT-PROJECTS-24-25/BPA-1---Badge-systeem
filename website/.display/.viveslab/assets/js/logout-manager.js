// logout-manager.js
// This script manages the logout state to prevent immediate re-login

// Store logout timestamp in localStorage when the user logs out
function recordLogout() {
    localStorage.setItem('lastLogout', Date.now().toString());
    localStorage.setItem('lastScannedCard', ''); // Clear the last scanned card
    
    // Redirect immediately - the localStorage is set synchronously
    window.location.href = 'index.php';
    
    return false; // Prevent default link behavior
}

// Check if a logout was recent (within the last 2 seconds)
function wasRecentlyLoggedOut() {
    const lastLogout = localStorage.getItem('lastLogout');
    if (!lastLogout) return false;
    
    const logoutTime = parseInt(lastLogout);
    const currentTime = Date.now();
    const timeSinceLogout = currentTime - logoutTime;
    
    // Consider "recent" if within the last 2 seconds
    return timeSinceLogout < 2000;
}

// Attach to all logout buttons when the page loads
document.addEventListener('DOMContentLoaded', function() {
    // Find all logout buttons and attach the recordLogout function
    const logoutButtons = document.querySelectorAll('.logout-btn');
    logoutButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            recordLogout();
        });
    });
    
    // Also check if we were recently logged out
    if (wasRecentlyLoggedOut()) {
        // If recently logged out, make sure the scanner is visible
        const scannerContainer = document.querySelector('.scanner-container');
        if (scannerContainer) {
            scannerContainer.style.display = 'flex';
        }
        
        // And that the user info container is hidden
        const userInfoContainer = document.getElementById('user-info-container');
        if (userInfoContainer) {
            userInfoContainer.style.display = 'none';
        }
        
        console.log('Recent logout detected, reset scan state');
    }
});
