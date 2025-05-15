#!/bin/bash

# Set display
export DISPLAY=:0

# Kill existing Chrome instances
killall chromium-browser 2>/dev/null
killall chromium 2>/dev/null
sleep 0.2

# Hide cursor with unclutter
apt-get install -y unclutter
unclutter -idle 0 -root &

# Disable screensaver
xset s off
xset s noblank
xset -dpms

# Create Chrome profile directory
KIOSK_DIR="/home/student/.config/chromium-kiosk"
mkdir -p "$KIOSK_DIR/Default"

# Create a more complete preferences file to properly disable translation
cat > "$KIOSK_DIR/Default/Preferences" << EOF
{
   "translate": { 
      "enabled": false,
      "denied_translation_languages": ["en", "nl", "fr", "de"],
      "language_blacklist": ["en", "nl", "fr", "de"]
   },
   "translate_site_blacklist": ["3dprintersmaaklabvives.be"],
   "browser": {
      "enable_spellchecking": false
   },
   "webkit": {
      "webprefs": {
         "text_areas_are_resizable": false
      }
   }
}
EOF

# Create custom CSS file to disable text selection
mkdir -p "$KIOSK_DIR/Default/User StyleSheets"
cat > "$KIOSK_DIR/Default/User StyleSheets/Custom.css" << EOF
* {
  -webkit-user-select: none !important;
  -moz-user-select: none !important;
  -ms-user-select: none !important;
  user-select: none !important;
  cursor: none !important;
  touch-action: manipulation !important;
}
::selection { background: transparent !important; color: inherit !important; }
::-moz-selection { background: transparent !important; color: inherit !important; }

/* Voorkom dat invoervelden en knoppen speciale hover/focus states krijgen */
button, input, select, textarea, a {
  pointer-events: auto !important;
  outline: none !important;
}

/* Schakel zoom uit op mobiele apparaten */
html, body {
  touch-action: manipulation !important;
  overflow: hidden !important;
}
EOF

# Clean up any previous Chrome data to ensure settings take effect
rm -rf "$KIOSK_DIR/Default/Extensions"
rm -rf "$KIOSK_DIR/Default/Service Worker"
rm -rf "$KIOSK_DIR/Default/Session Storage"

# Launch Chrome in kiosk mode with enhanced flags to disable translate and other unwanted features
chromium-browser \
  --kiosk \
  --incognito \
  --disable-translate \
  --disable-features=TranslateUI,TouchpadOverscrollHistoryNavigation,TouchscreenOverscrollHistoryNavigation \
  --disable-client-side-phishing-detection \
  --disable-infobars \
  --disable-pinch \
  --overscroll-history-navigation=0 \
  --disable-gesture-typing \
  --disable-gesture-editing \
  --disable-touch-drag-drop \
  --disable-touch-adjustment \
  --no-first-run \
  --noerrdialogs \
  --disable-context-menu \
  --no-default-browser-check \
  --no-sandbox \
  --user-data-dir="$KIOSK_DIR" \
  --app=https://3dprintersmaaklabvives.be/rpidisplay/15_05
