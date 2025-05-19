/**
 * Centralized timeout configuration for all pages
 * 
 * This file contains settings for inactivity timeouts across different page types.
 * Include this file before initializing the InactivityManager to ensure consistent timeout values.
 */

const TIMEOUT_CONFIG = {
    // Default timeout in seconds
    DEFAULT_TIMEOUT: 90,
    
    // Timeout for admin pages in seconds
    ADMIN_TIMEOUT: 45,
    
    // Timeout for reservation pages in seconds
    RESERVATION_TIMEOUT: 90,
    
    // Warning time in seconds (how long the warning dialog is displayed)
    WARNING_TIME: 15,
    
    // Default redirect URL
    DEFAULT_REDIRECT_URL: 'index.php'
};
