/**
 * prevent-interactions.js
 * 
 * Dit bestand bevat JavaScript code die voorkomt dat gebruikers tekst kunnen selecteren,
 * gebruik kunnen maken van context menu's of andere ongewenste interacties kunnen hebben 
 * op een touchscreen kiosk.
 */

(function() {
    // Functie die wordt uitgevoerd bij laden van de pagina
    function setupKioskMode() {
        // Voorkom contextmenu (rechtermuisklik)
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        }, true);
        
        // Voorkom tekstselectie
        document.addEventListener('selectstart', function(e) {
            e.preventDefault();
            return false;
        }, true);
        
        // Voorkom slepen van elementen
        document.addEventListener('dragstart', function(e) {
            e.preventDefault();
            return false;
        }, true);
        
        // Voorkom dubbelklik (kan gebruikt worden voor tekst selectie)
        document.addEventListener('dblclick', function(e) {
            e.preventDefault();
            return false;
        }, true);
        
        // Voorkom gebruik van toetsenbord shortcuts
        document.addEventListener('keydown', function(e) {
            // Sta alleen numerieke invoer toe (voor pincodes en dergelijke)
            var isNumericInput = /^\d$/.test(e.key) || 
                                e.key === 'Backspace' ||
                                e.key === 'Delete' ||
                                e.key === 'Enter' ||
                                e.key === 'Tab';
                                
            var isArrowKey = e.key.includes('Arrow');
            
            var isAllowedKey = isNumericInput || isArrowKey;
            
            // Als we op een invoerveld of tekstgebied staan, sta toetsenbordinvoer toe
            var isFormElement = e.target.tagName === 'INPUT' || 
                               e.target.tagName === 'TEXTAREA' || 
                               e.target.tagName === 'SELECT';
            
            if (isFormElement && isAllowedKey) {
                // Sta toe - nodig voor invoervelden
                return true;
            }
            
            // Blokkeer alle andere toetsenbordinvoer
            if ((e.ctrlKey || e.metaKey || e.altKey) || !isFormElement) {
                e.preventDefault();
                return false;
            }
        }, true);
        
        // Voorkom dat touchscreen zoom kan worden gebruikt
        document.addEventListener('touchstart', function(e) {
            if (e.touches.length > 1) {
                e.preventDefault();
                return false;
            }
        }, { passive: false });
        
        // Schakel standaard touchacties uit
        document.addEventListener('touchmove', function(e) {
            // Sta scrollen toe, maar voorkom andere touchgebaren
            if (e.touches.length > 1) {
                e.preventDefault();
            }
        }, { passive: false });
        
        // Verberg muiscursor met JavaScript als extra maatregel
        document.documentElement.style.cursor = 'none';
        
        // Voor alle elementen
        var allElements = document.getElementsByTagName('*');
        for (var i = 0; i < allElements.length; i++) {
            allElements[i].style.cursor = 'none';
            allElements[i].style.userSelect = 'none';
            allElements[i].style.webkitUserSelect = 'none';
            allElements[i].style.MozUserSelect = 'none';
            allElements[i].style.msUserSelect = 'none';
        }
        
        // Verwijder focus van alle elementen bij aanraking
        document.addEventListener('touchend', function(e) {
            if (document.activeElement && document.activeElement !== document.body) {
                if (document.activeElement.tagName !== 'INPUT' && 
                    document.activeElement.tagName !== 'TEXTAREA' && 
                    document.activeElement.tagName !== 'SELECT') {
                    document.activeElement.blur();
                }
            }
        }, true);
    }

    // Voer de setup functie uit bij het laden van de pagina
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setupKioskMode();
    } else {
        document.addEventListener('DOMContentLoaded', setupKioskMode, false);
    }
    
    // Voor zekerheid nogmaals uitvoeren na volledig laden
    window.addEventListener('load', setupKioskMode, false);
})();
