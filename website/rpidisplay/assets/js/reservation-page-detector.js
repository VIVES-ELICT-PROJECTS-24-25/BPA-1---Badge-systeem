// reservation-page-detector.js
// This script prevents card scanning redirects when already viewing reservations

document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on the reservations view page
    const isReservationView = window.location.search.includes('view_reservations=1');
    const userInfoContainer = document.getElementById('user-info-container');
    const isDisplayingReservations = userInfoContainer && 
                                    userInfoContainer.style.display !== 'none' &&
                                    userInfoContainer.querySelector('.reservations-container');
    
    // Set a global flag that can be checked by the card scanning script
    window.isViewingReservations = isReservationView || isDisplayingReservations;
    
    console.log('Reservation page detector initialized, isViewingReservations:', window.isViewingReservations);
});
