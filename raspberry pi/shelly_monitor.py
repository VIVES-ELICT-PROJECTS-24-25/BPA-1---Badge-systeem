# Dit script houdt de status van de Shelly-apparaten bij in Firebase

import requests
import time
import datetime
import firebase_admin
from firebase_admin import credentials, db

# Shelly Plug IP configuratie
SHELLY_DEVICES = {
    "shelly1": {
        "name": "Shelly 1",
        "ip": "172.20.10.3"
    },
    "shelly2": {
        "name": "Shelly 2",
        "ip": "172.20.10.14"
    }
}

# Add these global variables after the device_locks declaration
last_processed_rfid_id = None
last_processed_timestamp = None

# Firebase configuratie
cred = credentials.Certificate("maaklab-project-firebase-adminsdk-fbsvc-6560598dd1.json")
firebase_admin.initialize_app(cred, {
    "databaseURL": "https://maaklab-project-default-rtdb.europe-west1.firebasedatabase.app"
})

# Firebase referenties
shellies_ref = db.reference("shellies")
web_commands_ref = db.reference("web_commands")  # NIEUW: web commands node

# Status lock bijhouden - voorkomt race conditions
device_locks = {}

# Functie om de status van een Shelly-apparaat op te halen
def get_shelly_status(ip_address):
    try:
        url = f"http://{ip_address}/status"
        response = requests.get(url, timeout=5)
        if response.status_code == 200:
            data = response.json()
            return {
                "state": "on" if data["relays"][0]["ison"] else "off",
                "online": True
            }
        return {"state": "unknown", "online": False}
    except Exception as e:
        print(f"Fout bij het ophalen van Shelly-status: {e}")
        return {"state": "unknown", "online": False}

# Functie om een Shelly-apparaat te schakelen
def control_shelly_device(device_id, device_config, target_state):
    try:
        print(f"Uitvoeren commando: {device_config['name']} -> {target_state}")
        url = f"http://{device_config['ip']}/relay/0?turn={target_state}"
        response = requests.get(url, timeout=5)
        
        if response.status_code == 200:
            print(f"Succesvol {device_config['name']} geschakeld naar {target_state}")
            timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            
            updates = {
                "state": target_state,
                "online": True,
                "command_processed": True,
                "last_toggled": timestamp
            }
            
            # Als het apparaat wordt ingeschakeld, update de start_time
            if target_state == "on":
                updates["start_time"] = timestamp
            
            # BELANGRIJK: Wacht kort om te zorgen dat de status daadwerkelijk is gewijzigd
            time.sleep(1)
            
            # Controleer of de status daadwerkelijk is gewijzigd
            check_status = get_shelly_status(device_config["ip"])
            if check_status["state"] == target_state:
                print(f"Status bevestigd: {device_config['name']} is nu {check_status['state']}")
            else:
                print(f"WAARSCHUWING: {device_config['name']} heeft niet de verwachte status!")
            
            # Update de status in Firebase
            shellies_ref.child(device_id).update(updates)
            return True
        else:
            print(f"Fout bij het schakelen van {device_config['name']}: HTTP {response.status_code}")
            shellies_ref.child(device_id).update({
                "online": False,
                "command_processed": True  # Markeer als verwerkt ondanks fout
            })
            return False
    except Exception as e:
        print(f"Fout bij het schakelen van {device_config['name']}: {e}")
        shellies_ref.child(device_id).update({
            "online": False,
            "command_processed": True  # Markeer als verwerkt ondanks fout
        })
        return False

# Functie om een Shelly-apparaat te schakelen op basis van Firebase-data
def process_shellies_commands():
    for device_id, device_config in SHELLY_DEVICES.items():
        # Haal huidige Firebase-status op
        device_ref = shellies_ref.child(device_id)
        firebase_data = device_ref.get() or {}
        
        if not firebase_data:
            # Initialiseer als er nog geen data is
            current_status = get_shelly_status(device_config["ip"])
            timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            
            initial_data = {
                "name": device_config["name"],
                "ip": device_config["ip"],
                "state": current_status["state"],
                "online": current_status["online"],
                "last_toggled": timestamp,
                "command_processed": True  # BELANGRIJK: Geeft aan dat de status is verwerkt
            }
            
            if current_status["state"] == "on":
                initial_data["start_time"] = timestamp
                
            device_ref.set(initial_data)
            print(f"Geïnitialiseerd {device_config['name']} in Firebase")
            continue
            
        # Haal huidige apparaatstatus op
        current_status = get_shelly_status(device_config["ip"])
        
        # BELANGRIJK: Controleer of dit een nieuw commando is dat verwerkt moet worden
        if firebase_data.get("command_processed") is False:
            print(f"Nieuw commando gevonden voor {device_config['name']}: {firebase_data.get('state')}")
            
            # Vergeet de vorige state om te forceren dat het commando wordt uitgevoerd
            device_locks[device_id] = firebase_data.get('state')
            
            control_shelly_device(device_id, device_config, firebase_data.get('state'))
        else:
            # Geen nieuw commando - alleen status synchroniseren als nodig
            # Controleer eerst of de werkelijke status overeenkomt met wat in Firebase staat
            if current_status["online"] != firebase_data.get("online", False):
                device_ref.update({
                    "online": current_status["online"]
                })
                print(f"Online status bijgewerkt voor {device_config['name']}: {current_status['online']}")
            
            # Als er een discrepantie is tussen de werkelijke status en wat Firebase denkt
            # EN er is geen actief commando voor dit apparaat, update Firebase
            expected_state = device_locks.get(device_id)
            if (current_status["state"] != firebase_data.get("state") and 
                current_status["state"] in ["on", "off"] and
                expected_state != current_status["state"]):
                print(f"Status discrepantie voor {device_config['name']}: Firebase={firebase_data.get('state')}, Werkelijk={current_status['state']}")
                timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                
                updates = {
                    "state": current_status["state"],
                    "command_processed": True
                }
                
                # Als het apparaat nu aan is maar dat was het voorheen niet
                if current_status["state"] == "on" and firebase_data.get("state") != "on":
                    updates["start_time"] = timestamp
                
                device_ref.update(updates)
                print(f"Status in Firebase bijgewerkt naar de werkelijke status voor {device_config['name']}")

# NIEUW: Functie voor het verwerken van web commands
def process_web_commands():
    commands = web_commands_ref.get() or {}
    
    for device_id, command_data in commands.items():
        if device_id in SHELLY_DEVICES and command_data and command_data.get("command_processed") is False:
            target_state = command_data.get("state")
            
            if target_state in ["on", "off"]:
                print(f"Web commando gevonden voor {device_id}: {target_state}")
                
                # Update the source in the shellies node
                shellies_ref.child(device_id).update({
                    "source": "web"  # Mark that web has priority
                })
                
                # Uitvoeren van het commando
                success = control_shelly_device(device_id, SHELLY_DEVICES[device_id], target_state)
                
                # Update de shellies node met nieuwe status
                if success:
                    # Markeer het commando als verwerkt
                    web_commands_ref.child(device_id).update({
                        "command_processed": True,
                        "processed_at": datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                    })

# RFID-handler: Reageer op RFID-scans in Firebase en schakel de juiste Shelly's
# Modify the handle_rfid_scans function
def handle_rfid_scans():
    # RFID-kaart ID's
    RFID_ON_ID = "429343509312"  # Kaart om AAN te zetten
    RFID_OFF_ID = "563434264953"  # Kaart om UIT te zetten
    
    # Store the last processed RFID ID as a global variable
    global last_processed_rfid_id
    
    try:
        rfid_ref = db.reference("rfid_latest_scan")
        scan_data = rfid_ref.get()
        
        if scan_data:
            rfid_id = scan_data.get("id", "").strip()
            scan_timestamp = scan_data.get("timestamp", "")
            
            # Only process if this is a new scan (different ID or timestamp)
            if rfid_id and (rfid_id != last_processed_rfid_id or scan_timestamp != last_processed_timestamp):
                if rfid_id == RFID_ON_ID:
                    # Schakel alle apparaten AAN
                    for device_id in SHELLY_DEVICES:
                        timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                        shellies_ref.child(device_id).update({
                            "state": "on",
                            "command_processed": False,  # Nieuw commando markeren
                            "last_toggled": timestamp,
                            "start_time": timestamp,
                            "source": "rfid"  # Mark the command source
                        })
                    print(f"RFID-kaart gescand: alle apparaten AAN")
                    
                elif rfid_id == RFID_OFF_ID:
                    # Schakel alle apparaten UIT
                    for device_id in SHELLY_DEVICES:
                        shellies_ref.child(device_id).update({
                            "state": "off",
                            "command_processed": False,  # Nieuw commando markeren
                            "last_toggled": datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                            "source": "rfid"  # Mark the command source
                        })
                    print(f"RFID-kaart gescand: alle apparaten UIT")
                    
                last_processed_rfid_id = rfid_id
                last_processed_timestamp = scan_timestamp
                
                # Store the last processed scan info in Firebase
                db.reference("rfid_processed").set({
                    "id": rfid_id,
                    "timestamp": scan_timestamp,
                    "processed_at": datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                })
    except Exception as e:
        print(f"Fout bij het verwerken van RFID-scans: {e}")

# Hoofdlus
def main():
    print("Shelly monitor gestart. Druk Ctrl+C om te stoppen.")
    print("Dit script zal luisteren naar wijzigingen in Firebase en de Shelly-apparaten aansturen.")
    
    try:
        while True:
            # Controleer en reageer op wijzigingen in shellies node
            process_shellies_commands()
            
            # NIEUW: Controleer en reageer op wijzigingen in web_commands node
            process_web_commands()
            
            # Controleer en verwerk RFID-scans
            handle_rfid_scans()
            
            # Wacht voor de volgende check
            time.sleep(3)  # Verhoogd naar 3 seconden om minder polling te doen
    except KeyboardInterrupt:
        print("\nMonitor gestopt door gebruiker.")
    except Exception as e:
        print(f"Onverwachte fout: {e}")

if __name__ == "__main__":
    main()
