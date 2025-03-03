import requests
import time
import firebase_admin
from firebase_admin import credentials, db

# Shelly Plug IP
IP_ADDRESS = "http://192.168.0.250"  # Pas dit aan naar jouw Shelly IP

# Hardcoded RFID-kaart ID's
RFID_ON_ID = "429343509312"  # Vervang dit met de ID van de kaart om AAN te zetten
RFID_OFF_ID = "563434264953"  # Vervang dit met de ID van de kaart om UIT te zetten

# Firebase-configuratie
cred = credentials.Certificate("maaklab-project-firebase-adminsdk-fbsvc-6560598dd1.json")
firebase_admin.initialize_app(cred, {
    "databaseURL": "https://maaklab-project-default-rtdb.europe-west1.firebasedatabase.app"
})

# Functie om de Shelly Plug te schakelen
def control_shelly_plug(state):
    url = f"{IP_ADDRESS}/relay/0?turn={state}"
    response = requests.get(url)
    if response.status_code == 200:
        print(f"Shelly Plug is {state}")
    else:
        print(f"Fout bij schakelen van Shelly Plug naar {state}")

# Laatst verwerkte RFID-ID om duplicaten te voorkomen
last_processed_id = None

# Start monitoring Firebase RTDB
print("Monitoring Firebase voor RFID-scans...")
while True:
    try:
        ref = db.reference("rfid_latest_scan")
        scan_data = ref.get()
        
        if scan_data:
            rfid_id = scan_data.get("id", "").strip()
            
            if rfid_id and rfid_id != last_processed_id:
                if rfid_id == RFID_ON_ID:
                    control_shelly_plug("on")
                elif rfid_id == RFID_OFF_ID:
                    control_shelly_plug("off")
                
                # Update laatst verwerkte ID om dubbele uitvoeringen te voorkomen
                last_processed_id = rfid_id

        time.sleep(2)  # Vermijd te vaak polleren
    except KeyboardInterrupt:
        print("\nScript gestopt door gebruiker.")
        break
    except Exception as e:
        print(f"Fout: {e}")
        time.sleep(5)  # Wacht even voor retry bij fout
