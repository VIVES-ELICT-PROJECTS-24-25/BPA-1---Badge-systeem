#!/usr/bin/env python3

import time
import binascii
import signal
import logging
import os
import json
from logging.handlers import RotatingFileHandler
from datetime import datetime
import threading

# Hardware imports
import board
import busio
from digitalio import DigitalInOut
from adafruit_pn532.spi import PN532_SPI

# Firebase imports
import firebase_admin
from firebase_admin import credentials, db

class RFIDReader:
    def __init__(self, config_file="config.json"):
        """Initialize the RFID reader with configuration"""
        self.running = False
        self.last_read_uid = None
        self.last_read_time = 0
        self.reconnect_delay = 5  # Seconds to wait before reconnecting
        self.min_read_interval = 1  # Minimum seconds between readings of the same card
        self.pn532 = None
        self.firebase_initialized = False
        
        # Set up logging
        log_dir = "logs"
        os.makedirs(log_dir, exist_ok=True)
        self.setup_logging(log_dir)
        
        # Load configuration
        try:
            self.config = self.load_config(config_file)
            self.logger.info("Configuration loaded successfully")
        except Exception as e:
            self.logger.error(f"Error loading configuration: {e}")
            self.config = {
                "firebase_credentials_path": "/home/student/Documents/maaklab-project-firebase-adminsdk-fbsvc-6560598dd1.json",
                "firebase_database_url": "https://maaklab-project-default-rtdb.europe-west1.firebasedatabase.app",
                "firebase_path": "rfid_latest_scan",
                "cs_pin": "D5",
                "reset_pin": "D6",
                "scan_interval": 0.5,
                "read_timeout": 0.5,
                "heartbeat_interval": 300,  # 5 minutes
                "duplicate_timeout": 2  # Seconds before reading same card again
            }
            
        # Initialize heartbeat timer
        self.heartbeat_timer = None
        
    def load_config(self, config_file):
        """Load configuration from a JSON file"""
        if os.path.exists(config_file):
            with open(config_file, 'r') as f:
                return json.load(f)
        else:
            # Create default config file if it doesn't exist
            default_config = {
                "firebase_credentials_path": "/home/student/Documents/maaklab-project-firebase-adminsdk-fbsvc-6560598dd1.json",
                "firebase_database_url": "https://maaklab-project-default-rtdb.europe-west1.firebasedatabase.app",
                "firebase_path": "rfid_latest_scan",
                "cs_pin": "D5",
                "reset_pin": "D6", 
                "scan_interval": 0.5,
                "read_timeout": 0.5,
                "heartbeat_interval": 300,
                "duplicate_timeout": 2
            }
            with open(config_file, 'w') as f:
                json.dump(default_config, f, indent=4)
            return default_config

    def setup_logging(self, log_dir):
        """Set up rotating log files"""
        self.logger = logging.getLogger("RFIDReader")
        self.logger.setLevel(logging.INFO)
        
        # Console handler
        console_handler = logging.StreamHandler()
        console_handler.setLevel(logging.INFO)
        console_format = logging.Formatter('%(asctime)s - %(levelname)s - %(message)s')
        console_handler.setFormatter(console_format)
        
        # File handler (10 files, 1MB each)
        file_handler = RotatingFileHandler(
            os.path.join(log_dir, "rfid_reader.log"), 
            maxBytes=1024*1024, 
            backupCount=10
        )
        file_handler.setLevel(logging.DEBUG)
        file_format = logging.Formatter('%(asctime)s - %(levelname)s - %(message)s')
        file_handler.setFormatter(file_format)
        
        # Add handlers
        self.logger.addHandler(console_handler)
        self.logger.addHandler(file_handler)

    def initialize_firebase(self):
        """Initialize Firebase with retry mechanism"""
        # If we've previously flagged Firebase as initialized, return success
        if self.firebase_initialized:
            return True
            
        try:
            cred_path = self.config["firebase_credentials_path"]
            db_url = self.config["firebase_database_url"]
            
            if not os.path.exists(cred_path):
                self.logger.error(f"Firebase credentials file not found: {cred_path}")
                return False
            
            # Check if Firebase app is already initialized
            try:
                # This will throw an error if not initialized
                default_app = firebase_admin.get_app()
                # If we get here, Firebase is already initialized
                self.logger.info("Firebase was already initialized")
                self.firebase_initialized = True
                return True
            except ValueError:
                # Not initialized yet, continue with initialization
                pass
                    
            cred = credentials.Certificate(cred_path)
            firebase_admin.initialize_app(cred, {
                "databaseURL": db_url
            })
            
            # Test connection
            ref = db.reference("test_connection")
            ref.set({"timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S")})
            
            self.firebase_initialized = True
            self.logger.info("Firebase initialized successfully")
            return True
                
        except Exception as e:
            # Special handling for "app already exists" error
            if "The default Firebase app already exists" in str(e):
                self.logger.warning("Firebase was already initialized in another context")
                self.firebase_initialized = True
                return True
            else:
                self.logger.error(f"Failed to initialize Firebase: {e}")
                return False

    def initialize_pn532(self):
        """Initialize the PN532 RFID reader with retry mechanism"""
        try:
            # SPI configuration
            spi = busio.SPI(board.SCK, board.MOSI, board.MISO)
            
            # Convert string pin names to actual board pins
            try:
                cs_pin_name = self.config["cs_pin"]
                reset_pin_name = self.config["reset_pin"]
                
                # Get the actual pin objects from the board module
                cs_pin_obj = getattr(board, cs_pin_name)
                reset_pin_obj = getattr(board, reset_pin_name)
                
                # Configure PN532 with CS and reset pins
                cs_pin = DigitalInOut(cs_pin_obj)
                reset_pin = DigitalInOut(reset_pin_obj)
                
                self.logger.debug(f"Using pins: CS={cs_pin_name}, Reset={reset_pin_name}")
            except AttributeError as e:
                self.logger.error(f"Invalid pin name: {e}")
                return False
            
            # Create PN532 object
            self.pn532 = PN532_SPI(spi, cs_pin, debug=False, reset=reset_pin)
            
            # Check firmware version to verify communication
            firmware_data = self.pn532.firmware_version
            if firmware_data:
                self.logger.info(f"PN532 initialized with firmware version: {firmware_data[1]}.{firmware_data[2]}")
                
                # Configure PN532 to communicate with MiFare cards
                self.pn532.SAM_configuration()
                return True
            else:
                self.logger.error("Failed to get firmware version from PN532")
                return False
                
        except Exception as e:
            self.logger.error(f"Failed to initialize PN532: {e}")
            self.pn532 = None
            return False
    
    def send_to_firebase(self, uid_hex):
        """Send RFID data to Firebase with error handling"""
        if not self.firebase_initialized and not self.initialize_firebase():
            self.logger.error("Cannot send to Firebase - not initialized")
            return False
            
        try:
            ref = db.reference(self.config["firebase_path"])
            data = {
                "id": uid_hex,
                "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            }
            ref.set(data)
            self.logger.debug(f"Data sent to Firebase: {data}")
            return True
        except Exception as e:
            self.logger.error(f"Error sending data to Firebase: {e}")
            # Reset Firebase status so we'll try to reinitialize next time
            self.firebase_initialized = False
            return False
    
    def send_heartbeat(self):
        """Send a heartbeat to Firebase to indicate the service is running"""
        try:
            if self.firebase_initialized:
                ref = db.reference("rfid_reader_status")
                ref.set({
                    "status": "active",
                    "last_heartbeat": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                })
                self.logger.debug("Heartbeat sent")
        except Exception as e:
            self.logger.error(f"Error sending heartbeat: {e}")
            
        # Schedule next heartbeat
        if self.running:
            self.heartbeat_timer = threading.Timer(self.config["heartbeat_interval"], self.send_heartbeat)
            self.heartbeat_timer.daemon = True
            self.heartbeat_timer.start()
    
    def is_duplicate_read(self, uid):
        """Check if this is a duplicate read within the timeout period"""
        if uid == self.last_read_uid:
            current_time = time.time()
            if (current_time - self.last_read_time) < self.config["duplicate_timeout"]:
                return True
        return False
    
    def process_card(self, uid):
        """Process a detected RFID card"""
        uid_hex = binascii.hexlify(uid).decode('utf-8')
        
        # Check for duplicate reads
        if self.is_duplicate_read(uid_hex):
            self.logger.debug(f"Ignoring duplicate read: {uid_hex}")
            return
            
        # Update last read information
        self.last_read_uid = uid_hex
        self.last_read_time = time.time()
        
        self.logger.info(f"Card detected - ID: {uid_hex}")
        
        # Send to Firebase
        if self.send_to_firebase(uid_hex):
            self.logger.info("Data successfully sent to Firebase")
        else:
            self.logger.warning("Failed to send data to Firebase")
    
    def run(self):
        """Main loop for reading RFID cards"""
        self.running = True
        
        # Set up signal handlers for graceful shutdown
        signal.signal(signal.SIGINT, self.signal_handler)
        signal.signal(signal.SIGTERM, self.signal_handler)
        
        self.logger.info("Starting RFID reader service")
        
        # Initialize components
        firebase_ok = self.initialize_firebase()
        if not firebase_ok:
            self.logger.warning("Starting without Firebase connection")
        
        pn532_ok = self.initialize_pn532()
        if not pn532_ok:
            self.logger.error("Failed to initialize PN532. Exiting.")
            return
        
        # Start heartbeat
        self.send_heartbeat()
        
        # Main reading loop
        failure_count = 0
        while self.running:
            try:
                # Read card
                uid = self.pn532.read_passive_target(timeout=self.config["read_timeout"])
                
                if uid is not None:
                    failure_count = 0  # Reset failure counter on successful read
                    self.process_card(uid)
                else:
                    # No card detected, no need to log this
                    pass
                    
                # Small sleep to prevent CPU overuse
                time.sleep(0.01)
                
            except Exception as e:
                failure_count += 1
                self.logger.error(f"Error reading card: {e}")
                
                # After 5 consecutive failures, try to reinitialize
                if failure_count >= 5:
                    self.logger.warning("Multiple read failures, attempting to reinitialize PN532")
                    if self.initialize_pn532():
                        failure_count = 0
                    else:
                        # If reinitialization fails, wait before retry
                        time.sleep(self.reconnect_delay)
        
        self.logger.info("RFID reader service stopped")
    
    def signal_handler(self, sig, frame):
        """Handle signals for graceful shutdown"""
        self.logger.info(f"Received signal {sig}, shutting down...")
        self.shutdown()
    
    def shutdown(self):
        """Clean up and shut down the service"""
        self.running = False
        
        # Cancel heartbeat timer if active
        if self.heartbeat_timer:
            self.heartbeat_timer.cancel()
        
        # Update status in Firebase
        try:
            if self.firebase_initialized:
                ref = db.reference("rfid_reader_status")
                ref.set({
                    "status": "inactive",
                    "shutdown_time": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                })
        except:
            pass  # Ignore errors during shutdown
            
        self.logger.info("RFID reader service shutdown complete")

if __name__ == "__main__":
    reader = RFIDReader()
    reader.run()
