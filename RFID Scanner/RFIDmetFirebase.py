#!/usr/bin/env python3

import time
import binascii
import board
import busio
from digitalio import DigitalInOut
from adafruit_pn532.spi import PN532_SPI
import firebase_admin
from firebase_admin import credentials, db

# Correct path to the credentials file
cred = credentials.Certificate("/home/student/Documents/maaklab-project-firebase-adminsdk-fbsvc-6560598dd1.json")
firebase_admin.initialize_app(cred, {
    "databaseURL": "https://maaklab-project-default-rtdb.europe-west1.firebasedatabase.app"
})

# SPI configuration
spi = busio.SPI(board.SCK, board.MOSI, board.MISO)

# Configure PN532 with CS (chip select) on GPIO pin D5
cs_pin = DigitalInOut(board.D5)  # Adjust this to your wiring
reset_pin = DigitalInOut(board.D6)  # Optional, adjust as needed

# Create and initialize PN532 object
pn532 = PN532_SPI(spi, cs_pin, debug=False, reset=reset_pin)

# Configure the PN532
firmware_data = pn532.firmware_version
print("Found PN532 with firmware version: {0}.{1}".format(firmware_data[1], firmware_data[2]))

# Configure PN532 to communicate with MiFare cards
pn532.SAM_configuration()

print("RFID-lezer gestart. Plaats een kaart of tag om te scannen...")
print("Press Ctrl+C to exit.")

try:
    while True:
        print("Wacht op een kaart...")
        # Check if a card is present
        uid = pn532.read_passive_target(timeout=0.5)
        
        # If no card is found, retry
        if uid is None:
            print(".", end="", flush=True)
            continue
        
        # Card detected
        uid_hex = binascii.hexlify(uid).decode('utf-8')
        print("\nKaart gedetecteerd!")
        print(f"Kaart ID: {uid_hex}")
        
        # Data naar Firebase sturen
        ref = db.reference("rfid_latest_scan")
        ref.set({
            "id": uid_hex,
            "timestamp": time.strftime("%Y-%m-%d %H:%M:%S")
        })
        
        print("Wacht op de volgende scan...")
        # Wait a bit before scanning again to avoid multiple reads
        time.sleep(2)
        
except KeyboardInterrupt:
    print("\nProgramma gestopt door gebruiker.")
except Exception as e:
    print(f"Fout opgetreden: {e}")
