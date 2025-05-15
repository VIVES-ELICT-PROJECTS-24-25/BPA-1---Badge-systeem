/**
 * Inactivity Timeout Management
 * 
 * This script handles user inactivity timeouts throughout the application.
 * Different screens have different timeout durations:
 * - Login screens: 60 seconds
 * - Reservation screens: 120 seconds (2 min)
 * - Admin screens: 30 seconds
 * 
 * After a period of inactivity, a confirmation dialog appears with a 15-second countdown,
 * asking if the user is still there. If no action is taken, the user is redirected to the home page.
 */

class InactivityManager {
    constructor(options = {}) {
        // Default settings
        this.settings = {
            timeout: 60, // Default timeout in seconds
            warningTime: 15, // Time in seconds for the warning dialog
            redirectUrl: 'index.php', // Default redirect URL
            isAdminPage: false, // Whether this is an admin page
            isReservationPage: false, // Whether this is a reservation page
            ...options // Override with provided options
        };

        // Adjust timeout based on page type
        if (this.settings.isAdminPage) {
            this.settings.timeout = 30; // 30 seconds for admin pages
        } else if (this.settings.isReservationPage) {
            this.settings.timeout = 120; // 2 minutes for reservation pages
        }

        // State variables
        this.timeoutId = null;
        this.warningTimeoutId = null;
        this.warningDialogVisible = false;
        this.countdownInterval = null;
        this.secondsRemaining = this.settings.warningTime;
        
        // Initialize
        this.createWarningDialog();
        this.startTimer();
        this.addEventListeners();
        
        console.log(`Inactivity timeout initialized: ${this.settings.timeout} seconds`);
    }

    // Create the warning dialog element
    createWarningDialog() {
        // Create the dialog container if it doesn't exist
        if (!document.getElementById('inactivity-warning')) {
            const dialogHTML = `
                <div id="inactivity-warning" class="inactivity-warning hidden">
                    <div class="inactivity-content">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Bent u er nog?</h3>
                        <p>U wordt automatisch uitgelogd over <span id="countdown">${this.settings.warningTime}</span> seconden.</p>
                        <button id="stay-active-btn">Ja, ik ben er nog</button>
                    </div>
                </div>
            `;
            
            // Add the dialog HTML to the document body
            const dialogContainer = document.createElement('div');
            dialogContainer.innerHTML = dialogHTML;
            document.body.appendChild(dialogContainer.firstElementChild);
            
            // Add event listener to the button
            document.getElementById('stay-active-btn').addEventListener('click', () => {
                this.resetTimer();
                this.hideWarningDialog();
            });
        }
    }
    
    // Add necessary event listeners to detect user activity
    addEventListeners() {
        // List of events to track for user activity
        const events = [
            'mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 
            'click', 'keydown', 'touchmove'
        ];
        
        // Add all event listeners
        events.forEach(event => {
            document.addEventListener(event, () => this.resetTimer(), { passive: true });
        });
    }
    
    // Start the inactivity timer
    startTimer() {
        if (this.timeoutId) {
            clearTimeout(this.timeoutId);
        }
        
        this.timeoutId = setTimeout(() => {
            this.showWarningDialog();
        }, this.settings.timeout * 1000);
    }
    
    // Reset the inactivity timer
    resetTimer() {
        // Only reset the timer if the warning dialog is not visible
        if (!this.warningDialogVisible) {
            if (this.timeoutId) {
                clearTimeout(this.timeoutId);
            }
            this.startTimer();
        }
    }
    
    // Show the warning dialog with countdown
    showWarningDialog() {
        const warningElement = document.getElementById('inactivity-warning');
        if (!warningElement) return;
        
        this.warningDialogVisible = true;
        warningElement.classList.remove('hidden');
        
        // Reset and start the countdown
        this.secondsRemaining = this.settings.warningTime;
        document.getElementById('countdown').textContent = this.secondsRemaining;
        
        // Update the countdown every second
        this.countdownInterval = setInterval(() => {
            this.secondsRemaining--;
            document.getElementById('countdown').textContent = this.secondsRemaining;
            
            if (this.secondsRemaining <= 0) {
                this.redirect();
            }
        }, 1000);
    }
    
    // Hide the warning dialog
    hideWarningDialog() {
        const warningElement = document.getElementById('inactivity-warning');
        if (!warningElement) return;
        
        this.warningDialogVisible = false;
        warningElement.classList.add('hidden');
        
        // Stop the countdown
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
            this.countdownInterval = null;
        }
    }
    
    // Redirect to the specified URL
    redirect() {
        // Clear all timers and intervals
        if (this.timeoutId) {
            clearTimeout(this.timeoutId);
        }
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
        
        // Redirect
        window.location.href = this.settings.redirectUrl;
    }
}

// Add the CSS for the inactivity warning dialog
function addInactivityStyles() {
    const styleElement = document.createElement('style');
    styleElement.textContent = `
        .inactivity-warning {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 1;
            transition: opacity 0.3s ease;
        }
        
        .inactivity-warning.hidden {
            opacity: 0;
            pointer-events: none;
        }
        
        .inactivity-content {
            background-color: white;
            padding: 2rem;
            border-radius: 10px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .inactivity-content i {
            font-size: 3rem;
            color: #e74c3c;
            margin-bottom: 1rem;
        }
        
        .inactivity-content h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .inactivity-content p {
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }
        
        #stay-active-btn {
            background-color: #007ac9;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s ease;
        }
        
        #stay-active-btn:hover {
            background-color: #005a96;
        }
        
        #countdown {
            font-weight: bold;
        }
    `;
    document.head.appendChild(styleElement);
}

// Add the styles when the document loads
document.addEventListener('DOMContentLoaded', function() {
    addInactivityStyles();
});

// Export the InactivityManager for use in other scripts
window.InactivityManager = InactivityManager;
