import RPi.GPIO as GPIO
from mfrc522 import SimpleMFRC522
import time
import firebase_admin
from firebase_admin import credentials, db

# Expliciet BCM-modus instellen voor GPIO
GPIO.setmode(GPIO.BCM)

# Firebase-configuratie
cred = credentials.Certificate("/home/maaklab/Documents/maaklab-project-firebase-adminsdk-fbsvc-6560598dd1.json")
firebase_admin.initialize_app(cred, {
    "databaseURL": "https://maaklab-project-default-rtdb.europe-west1.firebasedatabase.app"
})

# Initialiseer RFID-lezer
reader = SimpleMFRC522()

try:
    print("RFID-lezer gestart. Plaats een kaart of tag om te scannen...")
    while True:
        print("Wacht op een kaart...")
        id_str, text = reader.read()
        
        print("\nKaart gedetecteerd!")
        print(f"Kaart ID: {id_str}")
        print(f"Gegevens: {text.strip()}")
        
        # Data naar Firebase sturen
        ref = db.reference("rfid_latest_scan")
        ref.set({
            "id": str(id_str),
            "text": text.strip() if text else "",
            "timestamp": time.strftime("%Y-%m-%d %H:%M:%S")
        })
        print("Wacht op de volgende scan...")
        time.sleep(2)  # Voorkomt dubbele uitlezing
except KeyboardInterrupt:
    print("\nProgramma gestopt door gebruiker.")
except Exception as e:
    print(f"Fout opgetreden: {e}")
finally:
    GPIO.cleanup()
