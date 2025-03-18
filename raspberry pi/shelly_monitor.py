# Dit script houdt de status van de Shelly-apparaten bij in Firebase
# Met toegevoegde functionaliteit voor verbruiksgegevens

import requests
import time
import datetime
import firebase_admin
from firebase_admin import credentials, db

# Shelly Plug IP configuratie
SHELLY_DEVICES = {
    "shelly1": {
        "name": "Shelly 1",
        "ip": "192.168.0.219"
    },
    "shelly2": {
        "name": "Shelly 2",
        "ip": "192.168.0.248"
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
power_usage_ref = db.reference("power_usage")  # NIEUW: node voor verbruiksgegevens

# Status lock bijhouden - voorkomt race conditions
device_locks = {}

# NIEUW: Functie om stroomsterkte en spanning op te halen
def get_shelly_electrical_data(ip_address):
    try:
        # Probeer eerst de Gen2 RPC API
        url = f"http://{ip_address}/rpc/Switch.GetStatus?id=0"
        response = requests.get(url, timeout=5)
        
        if response.status_code == 200:
            data = response.json()
            voltage = data.get("voltage", 0)
            current = data.get("current", 0)
            
            # Bereken het wattage op basis van spanning en stroom (P = U * I)
            calculated_power = round(voltage * current, 2)
            
            # Vergelijk het berekende vermogen met het gerapporteerde vermogen
            reported_power = data.get("apower", 0)
            
            return {
                "voltage": voltage,
                "current": current,
                "calculated_power": calculated_power,
                "reported_power": reported_power,
                "total_energy": data.get("aenergy", {}).get("total", 0),
                "temperature": data.get("temperature", {}).get("tC", 0),
                "timestamp": datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                "valid_data": True
            }
            
        # Probeer oude API als Gen2 niet werkt
        url = f"http://{ip_address}/status"
        response = requests.get(url, timeout=5)
        
        if response.status_code == 200:
            data = response.json()
            meters = data.get("meters", [])
            if meters and len(meters) > 0:
                voltage = meters[0].get("voltage", 0)
                current = meters[0].get("current", 0)
                
                # Bereken het wattage op basis van spanning en stroom (P = U * I)
                calculated_power = round(voltage * current, 2)
                
                # Vergelijk het berekende vermogen met het gerapporteerde vermogen
                reported_power = meters[0].get("power", 0)
                
                return {
                    "voltage": voltage,
                    "current": current,
                    "calculated_power": calculated_power,
                    "reported_power": reported_power,
                    "total_energy": meters[0].get("total", 0),
                    "temperature": data.get("temperature", 0) if "temperature" in data else 0,
                    "timestamp": datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                    "valid_data": True
                }
                
        return {
            "voltage": 0,
            "current": 0,
            "calculated_power": 0,
            "reported_power": 0,
            "total_energy": 0,
            "temperature": 0,
            "timestamp": datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            "error": "Geen verbruiksgegevens beschikbaar",
            "valid_data": False
        }
        
    except Exception as e:
        print(f"Fout bij het ophalen van elektrische gegevens: {e}")
        return {
            "voltage": 0,
            "current": 0,
            "calculated_power": 0,
            "reported_power": 0,
            "total_energy": 0,
            "temperature": 0,
            "timestamp": datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            "error": str(e),
            "valid_data": False
        }

# Functie om de status van een Shelly-apparaat op te halen
def get_shelly_status(ip_address):
    try:
        # Probeer eerst de Gen2 RPC API
        url = f"http://{ip_address}/rpc/Switch.GetStatus?id=0"
        response = requests.get(url, timeout=5)
        
        if response.status_code == 200:
            data = response.json()
            if "output" in data:
                # Gen2 Shelly apparaat
                return {
                    "state": "on" if data["output"] else "off",
                    "online": True
                }
        
        # Probeer oude API als Gen2 niet werkt
        url = f"http://{ip_address}/status"
        response = requests.get(url, timeout=5)
        
        if response.status_code == 200:
            data = response.json()
            if "relays" in data and len(data["relays"]) > 0:
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
        
        # Probeer eerst de Gen2 RPC API
        gen2_url = f"http://{device_config['ip']}/rpc/Switch.Set?id=0&on={target_state == 'on'}"
        gen2_response = requests.get(gen2_url, timeout=5)
        
        if gen2_response.status_code == 200:
            print(f"Succesvol {device_config['name']} geschakeld via Gen2 API naar {target_state}")
        else:
            # Probeer oude API als Gen2 niet werkt
            url = f"http://{device_config['ip']}/relay/0?turn={target_state}"
            response = requests.get(url, timeout=5)
            
            if response.status_code != 200:
                print(f"Fout bij het schakelen van {device_config['name']}: HTTP {response.status_code}")
                shellies_ref.child(device_id).update({
                    "online": False,
                    "command_processed": True  # Markeer als verwerkt ondanks fout
                })
                return False
            
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
    except Exception as e:
        print(f"Fout bij het schakelen van {device_config['name']}: {e}")
        shellies_ref.child(device_id).update({
            "online": False,
            "command_processed": True  # Markeer als verwerkt ondanks fout
        })
        return False

# NIEUW: Aangepaste functie om verbruiksgegevens naar Firebase te sturen
def update_electrical_data():
    for device_id, device_config in SHELLY_DEVICES.items():
        # Haal de status op
        status = get_shelly_status(device_config["ip"])
        
        # Alleen verbruiksgegevens ophalen als het apparaat online is
        if status["online"]:
            electrical_data = get_shelly_electrical_data(device_config["ip"])
            
            # Update de elektrische gegevens in Firebase
            power_usage_ref.child(device_id).set(electrical_data)
            
            # Voeg ook de laatste gegevens toe aan de shellies node
            shellies_ref.child(device_id).update({
                "voltage": electrical_data["voltage"],
                "current": electrical_data["current"],
                "calculated_power": electrical_data["calculated_power"],
                "reported_power": electrical_data["reported_power"],
                "last_energy_reading": electrical_data["total_energy"],
                "electrical_updated_at": electrical_data["timestamp"]
            })
            
            # Alleen loggen als het apparaat aan staat en daadwerkelijk verbruik heeft
            if status["state"] == "on" and electrical_data["valid_data"]:
                print(f"Elektrische gegevens bijgewerkt voor {device_config['name']}: {electrical_data['calculated_power']} W (berekend), {electrical_data['reported_power']} W (gerapporteerd)")
        else:
            # Als het apparaat offline is, markeer dit in Firebase
            timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            electrical_data = {
                "voltage": 0,
                "current": 0,
                "calculated_power": 0,
                "reported_power": 0,
                "timestamp": timestamp,
                "device_state": "offline",
                "valid_data": False
            }
            power_usage_ref.child(device_id).update(electrical_data)
            
            # Update ook de shellies node
            shellies_ref.child(device_id).update({
                "voltage": 0,
                "current": 0,
                "calculated_power": 0,
                "reported_power": 0,
                "electrical_updated_at": timestamp
            })
            
            print(f"Apparaat {device_config['name']} is offline, elektrische gegevens op 0 gezet")

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
            
            # Haal elektrische gegevens op
            electrical_data = get_shelly_electrical_data(device_config["ip"])
            
            initial_data = {
                "name": device_config["name"],
                "ip": device_config["ip"],
                "state": current_status["state"],
                "online": current_status["online"],
                "last_toggled": timestamp,
                "command_processed": True,  # BELANGRIJK: Geeft aan dat de status is verwerkt
                "voltage": electrical_data["voltage"],
                "current": electrical_data["current"],
                "calculated_power": electrical_data["calculated_power"],
                "reported_power": electrical_data["reported_power"],
                "last_energy_reading": electrical_data["total_energy"],
                "electrical_updated_at": timestamp
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

# Functie voor het verwerken van web commands
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
def handle_rfid_scans():
    # RFID-kaart ID's
    RFID_ON_ID = "429343509312"  # Kaart om AAN te zetten
    RFID_OFF_ID = "563434264953"  # Kaart om UIT te zetten
    
    # Store the last processed RFID ID as a global variable
    global last_processed_rfid_id
    global last_processed_timestamp
    
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

# NIEUW: Functie om historische elektrische gegevens op te slaan
def store_historical_electrical_data():
    try:
        current_datetime = datetime.datetime.now()
        date_str = current_datetime.strftime("%Y-%m-%d")
        hour_str = current_datetime.strftime("%H")
        
        # Maak een referentie naar de historische elektrische gegevens
        history_ref = db.reference(f"electrical_data_history/{date_str}/{hour_str}")
        
        # Verzamel elektrische gegevens van alle apparaten
        for device_id, device_config in SHELLY_DEVICES.items():
            device_status = get_shelly_status(device_config["ip"])
            
            if device_status["online"]:
                electrical_data = get_shelly_electrical_data(device_config["ip"])
                
                # Voeg een timestamp toe aan de gegevens
                minute_second = current_datetime.strftime("%M:%S")
                history_ref.child(device_id).child(minute_second).set({
                    "voltage": electrical_data["voltage"],
                    "current": electrical_data["current"],
                    "calculated_power": electrical_data["calculated_power"],
                    "reported_power": electrical_data["reported_power"],
                    "total_energy": electrical_data["total_energy"],
                    "state": device_status["state"]
                })
                
                print(f"Historische elektrische gegevens opgeslagen voor {device_config['name']}")
    except Exception as e:
        print(f"Fout bij het opslaan van historische elektrische gegevens: {e}")

# Variabele om bij te houden wanneer we voor het laatst historische gegevens hebben opgeslagen
last_historical_update = datetime.datetime.now()

# Hoofdlus
def main():
    print("Shelly monitor gestart. Druk Ctrl+C om te stoppen.")
    print("Dit script zal luisteren naar wijzigingen in Firebase en de Shelly-apparaten aansturen.")
    print("Toegevoegd: Elektrische gegevens (spanning, stroom en berekend vermogen) worden naar Firebase verzonden.")
    
    # Initialiseer de variabele voor het bijhouden van de laatste update van elektrische gegevens
    last_electrical_update = datetime.datetime.now()
    
    # Initialiseer de variabele voor historische updates
    global last_historical_update
    
    try:
        while True:
            # Controleer en reageer op wijzigingen in shellies node
            process_shellies_commands()
            
            # Controleer en reageer op wijzigingen in web_commands node
            process_web_commands()
            
            # Controleer en verwerk RFID-scans
            handle_rfid_scans()
            
            # NIEUW: Update elektrische gegevens elke 10 seconden
            current_time = datetime.datetime.now()
            if (current_time - last_electrical_update).total_seconds() >= 10:
                update_electrical_data()
                last_electrical_update = current_time
            
            # NIEUW: Sla historische gegevens op elke 5 minuten
            if (current_time - last_historical_update).total_seconds() >= 300:  # 5 minuten = 300 seconden
                store_historical_electrical_data()
                last_historical_update = current_time
            
            # Wacht voor de volgende check
            time.sleep(3)  # Verhoogd naar 3 seconden om minder polling te doen
    except KeyboardInterrupt:
        print("\nMonitor gestopt door gebruiker.")
    except Exception as e:
        print(f"Onverwachte fout: {e}")

if __name__ == "__main__":
    main()
