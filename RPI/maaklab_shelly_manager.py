#!/usr/bin/env python3
# maaklab_shelly_manager.py - Geoptimaliseerd script
# Frequentie: 20 sec voor normale controle, 5 min voor verlopen reserveringen

import requests
import time
import datetime
import logging
import sys
import os
import json
from threading import Thread
import firebase_admin
from firebase_admin import credentials, db

# Bepaal de home directory van de huidige gebruiker
HOME_DIR = os.path.expanduser("~")
LOG_FILE = os.path.join(HOME_DIR, "maaklab_shelly_manager.log")

# Configureer logging
os.makedirs(os.path.dirname(LOG_FILE), exist_ok=True)
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(LOG_FILE),
        logging.StreamHandler(sys.stdout)
    ]
)

logger = logging.getLogger("MaakLabManager")

# API configuratie
API_BASE_URL = "https://3dprintersmaaklabvives.be/rpidisplay/14_05/api"
API_TOKEN = "SUlhsg673GSbgsJYS6352jkdaLK"

# Firebase configuratie
FIREBASE_CRED_PATH = os.path.join(HOME_DIR, "Documents/maaklab-project-firebase-adminsdk-fbsvc-6560598dd1.json")
FIREBASE_DB_URL = "https://maaklab-project-default-rtdb.europe-west1.firebasedatabase.app"

# Constanten
POWER_THRESHOLD = 10          # Watt
STANDARD_CHECK_DELAY = 20     # Seconden voor normale controles
EXPIRED_CHECK_DELAY = 60     # Seconden (5 minuten) voor verlopen reserveringen
FIREBASE_POLL_INTERVAL = 0.5  # Seconden voor Firebase polling

class ShellyManager:
    def __init__(self):
        self.active_printers = {}        # Dict om actieve printers bij te houden
        self.firebase_initialized = False
        self.should_run = True           # Flag voor threads
        self.seen_print_commands = set() # Houdt bij welke start commando's we al hebben verwerkt
        self.last_expired_check = 0      # Timestamp van laatste controle voor verlopen reserveringen
        
        # Initialiseer Firebase
        self.initialize_firebase()
        
        # Start Firebase polling thread
        self.start_firebase_polling()
        
    def initialize_firebase(self):
        """Initialiseer de Firebase verbinding"""
        try:
            # Controleer of Firebase reeds geïnitlaliseerd is
            if not firebase_admin._apps:
                cred = credentials.Certificate(FIREBASE_CRED_PATH)
                firebase_admin.initialize_app(cred, {
                    'databaseURL': FIREBASE_DB_URL
                })
            
            self.firebase_initialized = True
            logger.info("Firebase succesvol geïnitialiseerd")
            return True
        except Exception as e:
            logger.error(f"Fout bij het initialiseren van Firebase: {e}")
            return False
    
    def start_firebase_polling(self):
        """Start een thread om continu Firebase te pollen voor nieuwe printopdrachten"""
        Thread(target=self._firebase_polling_thread, daemon=True).start()
        logger.info("Firebase polling thread gestart - monitort nieuwe printopdrachten")
    
    def _firebase_polling_thread(self):
        """Continu pollen van Firebase voor nieuwe printopdrachten"""
        logger.info("Start continu monitoren van Firebase voor nieuwe printopdrachten")
        
        # Referenties naar Firebase paden
        commands_ref = db.reference('print_start_commands')
        active_prints_ref = db.reference('active_prints')
        
        while self.should_run:
            try:
                # Controleer op nieuwe directe commando's
                commands_data = commands_ref.get()
                if commands_data:
                    for cmd_id, cmd_info in commands_data.items():
                        # Alleen nieuwe, nog niet verwerkte commando's
                        if cmd_id not in self.seen_print_commands and cmd_info.get('status') == 'pending':
                            logger.info(f"NIEUWE PRINTOPDRACHT GEDETECTEERD #{cmd_id}")
                            
                            # Direct verwerken
                            reservation_id = cmd_info.get('reservation_id', cmd_id)
                            self._direct_shelly_activation(reservation_id)
                            
                            # Markeer als verwerkt
                            self.seen_print_commands.add(cmd_id)
                            commands_ref.child(cmd_id).update({
                                'status': 'processing',
                                'received_time': datetime.datetime.now().isoformat()
                            })
                
                # Controleer op nieuwe prints in active_prints
                prints_data = active_prints_ref.get()
                if prints_data:
                    for print_id, print_info in prints_data.items():
                        if (print_id not in self.seen_print_commands and 
                            print_info.get('newly_added', False) and 
                            print_info.get('print_started', False)):
                            
                            logger.info(f"NIEUWE PRINT GEDETECTEERD in active_prints #{print_id}")
                            
                            # Direct verwerken
                            self._direct_shelly_activation(print_id)
                            
                            # Markeer als verwerkt
                            self.seen_print_commands.add(print_id)
                            active_prints_ref.child(print_id).update({
                                'newly_added': False,
                                'status': 'activating'
                            })
            
            except Exception as e:
                logger.error(f"Fout in Firebase polling: {e}")
            
            # Korte wachttijd voor volgende poll
            time.sleep(FIREBASE_POLL_INTERVAL)
    
    def _direct_shelly_activation(self, reservation_id):
        """Activeer Shelly direct, zonder wachttijd"""
        try:
            logger.info(f"DIRECTE SHELLY ACTIVERING VOOR #{reservation_id} GESTART")
            
            # Haal reserveringsdetails op
            url = f"{API_BASE_URL}/get_reservation_details.php?token={API_TOKEN}&reservation_id={reservation_id}"
            logger.info(f"Ophalen reserveringsdetails via API")
            
            response = requests.get(url, timeout=10)
            
            if response.status_code != 200:
                logger.error(f"API foutcode {response.status_code} bij ophalen reserveringsdetails")
                return
            
            data = response.json()
            
            if 'error' in data:
                logger.error(f"API fout bij ophalen reserveringsdetails: {data['error']}")
                return
            
            if not data.get('success') or 'data' not in data:
                logger.error(f"Ongeldig API response format")
                return
            
            # Haal Shelly IP adres op
            reservation = data['data']
            shelly_ip = reservation.get('Netwerkadres')
            
            if not shelly_ip:
                logger.error(f"Geen Shelly IP adres gevonden voor reservering #{reservation_id}")
                return
            
            logger.info(f"Reserveringsdetails succesvol opgehaald. Shelly IP: {shelly_ip}")
            
            # Controleer huidige Shelly status
            logger.info(f"Controleren van huidige Shelly status: {shelly_ip}")
            status = self.get_shelly_status(shelly_ip)
            
            if not status["online"]:
                logger.error(f"Shelly {shelly_ip} is OFFLINE! Kan niet activeren")
                self.update_firebase(reservation_id, shelly_ip, status)
                return
            
            # Activeer de Shelly als deze nog niet aan staat
            if status["state"] != "on":
                logger.info(f"Shelly {shelly_ip} staat UIT. Nu activeren...")
                
                if self.control_shelly(shelly_ip, turn_on=True):
                    logger.info(f"Shelly {shelly_ip} is geactiveerd voor reservering #{reservation_id}")
                    
                    # Ververs de status
                    status = self.get_shelly_status(shelly_ip)
                else:
                    logger.error(f"FOUT bij activeren Shelly {shelly_ip}")
            else:
                logger.info(f"Shelly {shelly_ip} is al actief.")
            
            # Update Firebase
            self.update_firebase(reservation_id, shelly_ip, status)
            
            # Update commando status
            firebase_updates = {
                f"active_prints/{reservation_id}/shelly_activated": True,
                f"active_prints/{reservation_id}/activation_time": datetime.datetime.now().isoformat(),
                f"active_prints/{reservation_id}/status": "active",
                f"active_prints/{reservation_id}/power_watts": status["power"],
                f"print_start_commands/{reservation_id}/status": "completed",
                f"print_start_commands/{reservation_id}/completed_time": datetime.datetime.now().isoformat()
            }
            db.reference().update(firebase_updates)
            
            # Voeg toe aan actieve printers voor monitoring
            end_time = datetime.datetime.strptime(reservation['PRINT_END'], "%Y-%m-%d %H:%M:%S")
            self.active_printers[str(reservation_id)] = {
                "shelly_ip": shelly_ip,
                "end_time": end_time,
                "last_check": datetime.datetime.now(),
                "power": status["power"]
            }
            
            logger.info(f"DIRECTE SHELLY ACTIVERING VOOR #{reservation_id} VOLTOOID")
            
        except Exception as e:
            logger.error(f"Fout bij directe Shelly activering: {e}")
            
            # Probeer Firebase toch te updaten met foutmelding
            try:
                error_updates = {
                    f"active_prints/{reservation_id}/error": str(e),
                    f"active_prints/{reservation_id}/error_time": datetime.datetime.now().isoformat(),
                    f"print_start_commands/{reservation_id}/status": "error",
                    f"print_start_commands/{reservation_id}/error": str(e)
                }
                db.reference().update(error_updates)
            except:
                pass
    
    def get_active_reservations(self):
        """Haal actieve reserveringen op via de API"""
        try:
            url = f"{API_BASE_URL}/get_active_prints.php?token={API_TOKEN}"
            response = requests.get(url, timeout=10)
            
            if response.status_code != 200:
                logger.error(f"API foutcode: {response.status_code}")
                return []
            
            data = response.json()
            
            if 'error' in data:
                logger.error(f"API fout: {data['error']}")
                return []
                
            if 'success' in data and data['success'] and 'data' in data:
                reservations = data['data']
                logger.info(f"{len(reservations)} actieve reserveringen gevonden")
                return reservations
                
            logger.error("Onverwacht API response formaat")
            return []
            
        except Exception as e:
            logger.error(f"Fout bij API aanroep voor actieve reserveringen: {e}")
            return []
    
    def get_expired_reservations(self):
        """Haal verlopen reserveringen op die nog niet zijn afgehandeld"""
        try:
            url = f"{API_BASE_URL}/get_expired_prints.php?token={API_TOKEN}"
            response = requests.get(url, timeout=10)
            
            if response.status_code != 200:
                logger.error(f"API foutcode bij verlopen reserveringen: {response.status_code}")
                return []
                
            data = response.json()
            
            if 'error' in data:
                logger.error(f"API fout bij verlopen reserveringen: {data['error']}")
                return []
                
            if 'success' in data and data['success'] and 'data' in data:
                expired = data['data']
                if expired:
                    logger.info(f"{len(expired)} verlopen reserveringen gevonden die nog actief zijn")
                return expired
                
            return []
            
        except Exception as e:
            logger.error(f"Fout bij API aanroep voor verlopen reserveringen: {e}")
            return []
    
    def update_reservation_status(self, reservation_id, completed=False):
        """Update de status van een reservering via de API"""
        try:
            url = f"{API_BASE_URL}/update_print_status.php"
            data = {
                'token': API_TOKEN,
                'reservation_id': reservation_id,
                'completed': completed
            }
            
            response = requests.post(url, json=data, timeout=10)
            
            if response.status_code != 200:
                logger.error(f"API foutcode bij status update: {response.status_code}")
                return False
                
            result = response.json()
            
            if 'error' in result:
                logger.error(f"API fout bij status update: {result['error']}")
                return False
                
            if 'success' in result and result['success']:
                logger.info(f"Reservering #{reservation_id} status bijgewerkt via API")
                return True
                
            logger.error("Onverwacht API response formaat bij status update")
            return False
            
        except Exception as e:
            logger.error(f"Fout bij API aanroep voor status update: {e}")
            return False
    
    def get_shelly_status(self, ip_address):
        """Haal de status op van een Shelly apparaat"""
        try:
            # Probeer eerst Gen2 API
            url = f"http://{ip_address}/rpc/Switch.GetStatus?id=0"
            response = requests.get(url, timeout=5)
            
            if response.status_code == 200:
                data = response.json()
                power = data.get("apower", 0)
                
                return {
                    "state": "on" if data.get("output", False) else "off",
                    "online": True,
                    "power": power
                }
            
            # Probeer oude API
            url = f"http://{ip_address}/status"
            response = requests.get(url, timeout=5)
            
            if response.status_code == 200:
                data = response.json()
                power = 0
                
                if "meters" in data and len(data["meters"]) > 0:
                    power = data["meters"][0].get("power", 0)
                
                return {
                    "state": "on" if data["relays"][0]["ison"] else "off",
                    "online": True,
                    "power": power
                }
                
            return {"state": "unknown", "online": False, "power": 0}
        except Exception as e:
            logger.error(f"Fout bij ophalen van Shelly status {ip_address}: {e}")
            return {"state": "unknown", "online": False, "power": 0}
    
    def control_shelly(self, ip_address, turn_on=True):
        """Bedien een Shelly apparaat (aan/uit)"""
        try:
            action = "on" if turn_on else "off"
            logger.info(f"Shelly {ip_address} wordt {action} gezet")
            
            # Probeer eerst Gen2 API
            gen2_url = f"http://{ip_address}/rpc/Switch.Set?id=0&on={str(turn_on).lower()}"
            gen2_response = requests.get(gen2_url, timeout=5)
            
            if gen2_response.status_code == 200:
                logger.info(f"Shelly {ip_address} succesvol {action} gezet via Gen2 API")
                return True
            
            # Probeer oude API
            legacy_url = f"http://{ip_address}/relay/0?turn={action}"
            response = requests.get(legacy_url, timeout=5)
            
            if response.status_code == 200:
                logger.info(f"Shelly {ip_address} succesvol {action} gezet via legacy API")
                return True
                
            logger.error(f"Kon Shelly {ip_address} niet {action} zetten")
            return False
        except Exception as e:
            logger.error(f"Fout bij schakelen van Shelly {ip_address}: {e}")
            return False
    
    def update_firebase(self, reservation_id, shelly_ip, status):
        """Update de status in Firebase"""
        if not self.firebase_initialized:
            return False
            
        try:
            shelly_id = f"shelly{reservation_id}"
            timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            
            # Update de shellies node
            shellies_ref = db.reference(f"shellies/{shelly_id}")
            shellies_ref.update({
                "ip": shelly_ip,
                "state": status["state"],
                "online": status["online"],
                "reported_power": status["power"],
                "last_update": timestamp,
                "reservation_id": reservation_id
            })
            
            # Update active_prints node
            active_prints_ref = db.reference(f"active_prints/{reservation_id}")
            active_prints_ref.update({
                "shelly_id": shelly_id,
                "last_check": timestamp,
                "reported_power": status["power"],
                "power_watts": status["power"]
            })
            
            logger.info(f"Firebase bijgewerkt voor reservering #{reservation_id} met power: {status['power']}W")
            return True
        except Exception as e:
            logger.error(f"Fout bij bijwerken Firebase: {e}")
            return False
    
    def mark_print_completed(self, reservation_id, shelly_ip, power_value, reason="low_power_auto_off"):
        """Markeer een print als voltooid in Firebase en database"""
        if not self.firebase_initialized:
            return False
            
        try:
            shelly_id = f"shelly{reservation_id}"
            timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            
            # Update de shellies node
            shellies_ref = db.reference(f"shellies/{shelly_id}")
            shellies_ref.update({
                "state": "off",
                "command_processed": False,
                "last_update": timestamp,
                "last_power_reading": power_value
            })
            
            # Update active_prints node
            active_prints_ref = db.reference(f"active_prints/{reservation_id}")
            active_prints_ref.update({
                "status": "completed",
                "power_off_time": timestamp,
                "final_power_reading": power_value,
                "power_watts": power_value,
                "completion_reason": reason
            })
            
            # Update database via API
            self.update_reservation_status(reservation_id, completed=True)
            
            logger.info(f"Print voor reservering #{reservation_id} gemarkeerd als voltooid. Laatste stroomverbruik: {power_value}W")
            return True
        except Exception as e:
            logger.error(f"Fout bij markeren print als voltooid: {e}")
            return False
    
    def check_and_manage_print(self, reservation):
        """Controleer en beheer een actieve print"""
        reservation_id = reservation['Reservatie_ID']
        printer_id = reservation['Printer_ID']
        shelly_ip = reservation['Netwerkadres']
        
        # Controleer of het IP adres bekend is
        if not shelly_ip:
            logger.warning(f"Geen Shelly IP gevonden voor reservering #{reservation_id}")
            return
            
        # Controleer of de reservering nog actief is
        now = datetime.datetime.now()
        end_time = datetime.datetime.strptime(reservation['PRINT_END'], "%Y-%m-%d %H:%M:%S")
        
        # Haal de Shelly status op
        status = self.get_shelly_status(shelly_ip)
        
        # Update Firebase met huidige status
        self.update_firebase(reservation_id, shelly_ip, status)
        
        # Controleer of de Shelly online is
        if not status["online"]:
            logger.warning(f"Shelly {shelly_ip} voor reservering #{reservation_id} is OFFLINE")
            return
            
        # Als de print nog niet is verlopen
        if now < end_time:
            time_left = end_time - now
            logger.info(f"Reservering #{reservation_id} heeft nog {time_left.total_seconds()/60:.1f} minuten, stroomverbruik: {status['power']}W")
            
            # Controleer of de Shelly aan staat
            if status["state"] != "on":
                logger.info(f"Shelly voor reservering #{reservation_id} staat UIT maar zou AAN moeten staan")
                self.control_shelly(shelly_ip, turn_on=True)
            
            # Bijhouden in actieve printers
            self.active_printers[str(reservation_id)] = {
                "shelly_ip": shelly_ip,
                "end_time": end_time,
                "last_check": now,
                "power": status["power"],
                "expired": False
            }
            return
            
        # De reservering is verlopen - controleer stroomverbruik
        logger.info(f"Reservering #{reservation_id} is VERLOPEN, stroomverbruik: {status['power']}W (drempel: {POWER_THRESHOLD}W)")
        
        # Als het verbruik onder de drempelwaarde is, schakel Shelly uit
        if status["power"] < POWER_THRESHOLD:
            logger.info(f"Verbruik onder drempelwaarde ({status['power']}W < {POWER_THRESHOLD}W), schakel Shelly UIT voor verlopen reservering #{reservation_id}")
            
            # Schakel de Shelly uit
            if self.control_shelly(shelly_ip, turn_on=False):
                # Markeer als voltooid met stroomverbruik
                self.mark_print_completed(reservation_id, shelly_ip, status["power"], reason="low_power_expired")
                
                # Verwijder uit actieve printers
                str_id = str(reservation_id)
                if str_id in self.active_printers:
                    del self.active_printers[str_id]
        else:
            # Verbruik nog te hoog
            logger.warning(f"Reservering #{reservation_id} is VERLOPEN maar verbruik nog boven drempelwaarde ({status['power']}W > {POWER_THRESHOLD}W)")
            
            # Bijwerken laatste check met expired flag
            self.active_printers[str(reservation_id)] = {
                "shelly_ip": shelly_ip,
                "end_time": end_time,
                "last_check": now,
                "power": status["power"],
                "expired": True  # Markeer als verlopen voor andere controlefrequentie
            }
    
    def should_check_expired_now(self):
        """Bepaalt of we nu verlopen reserveringen moeten controleren"""
        now = time.time()
        if now - self.last_expired_check >= EXPIRED_CHECK_DELAY:
            self.last_expired_check = now
            return True
        return False
    
    def check_expired_reservations(self):
        """Controleer verlopen reserveringen (maar alleen elke 5 minuten)"""
        if not self.should_check_expired_now():
            return
            
        logger.info("Controle op verlopen reserveringen (5-minuten interval)")
        expired = self.get_expired_reservations()
        
        for reservation in expired:
            logger.info(f"Controle op verlopen reservering #{reservation['Reservatie_ID']}")
            self.check_and_manage_print(reservation)
    
    def run(self):
        """Hoofdlus van de Shelly Manager"""
        logger.info("MaakLab Shelly Manager gestart met directe activering")
        
        try:
            while self.should_run:
                # Haal actieve reserveringen op
                active_reservations = self.get_active_reservations()
                
                # Beheer elke actieve reservering
                for reservation in active_reservations:
                    try:
                        self.check_and_manage_print(reservation)
                    except Exception as e:
                        logger.error(f"Fout bij beheren van reservering #{reservation['Reservatie_ID']}: {e}")
                
                # Controleer verlopen reserveringen (alleen elke 5 minuten)
                try:
                    self.check_expired_reservations()
                except Exception as e:
                    logger.error(f"Fout bij controleren verlopen reserveringen: {e}")
                
                # Wachten tot volgende controle
                logger.info(f"Wachten {STANDARD_CHECK_DELAY} seconden tot volgende controle")
                time.sleep(STANDARD_CHECK_DELAY)
                
        except KeyboardInterrupt:
            logger.info("Script gestopt door gebruiker")
            self.should_run = False
        except Exception as e:
            logger.error(f"Onverwachte fout: {e}")
            self.should_run = False

# Hoofdfunctie om het script te starten
if __name__ == "__main__":
    manager = ShellyManager()
    manager.run()
