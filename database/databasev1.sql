-- Maak de Vives tabel 
CREATE TABLE Vives (
    Vives_info_id INT PRIMARY KEY AUTO_INCREMENT,
    Kaart_nr VARCHAR(50), -- RFID kaartnummer
    Vives_nr VARCHAR(10) NOT NULL, -- Formaat zoals R0996329 of U0238762
    Richting VARCHAR(100) -- Richting binnen de hogeschool
);

-- Maak de Gebruiker tabel
CREATE TABLE Gebruiker (
    User_ID INT PRIMARY KEY AUTO_INCREMENT,
    Vives_Info_ID INT,
    Voornaam VARCHAR(50) NOT NULL,
    Naam VARCHAR(100) NOT NULL,
    Email VARCHAR(100) NOT NULL UNIQUE,
    Telnr VARCHAR(20),
    WW VARCHAR(255) NOT NULL, -- Gehasht wachtwoord, daarom 255 karakters
    rol VARCHAR(20) NOT NULL,
    Aanmaak_Acc DATE NOT NULL,
    Laatste_Aanmeld DATETIME,
    FOREIGN KEY (Vives_Info_ID) REFERENCES Vives(Vives_info_id)
);

-- Maak de Printer tabel
CREATE TABLE Printer (
    Printer_ID INT PRIMARY KEY AUTO_INCREMENT,
    Status VARCHAR(50) NOT NULL,
    Naam VARCHAR(100) NOT NULL,
    Laatste_Status_Change DATETIME,
    Info TEXT
);

-- Maak de Reservatie tabel
CREATE TABLE Reservatie (
    Reservatie_ID INT PRIMARY KEY AUTO_INCREMENT,
    User_ID INT NOT NULL,
    Printer_ID INT NOT NULL,
    Date_Time_res DATETIME NOT NULL,
    Pr_Start DATETIME NOT NULL,
    Pr_End DATETIME NOT NULL,
    Comment TEXT, -- Kan veel tekst bevatten
    Pin CHAR(8) NOT NULL, -- Exacte lengte van 8 cijfers
    Filament_Kleur VARCHAR(50),
    Filament_Type VARCHAR(50),
    FOREIGN KEY (User_ID) REFERENCES Gebruiker(User_ID),
    FOREIGN KEY (Printer_ID) REFERENCES Printer(Printer_ID)
);

-- Maak indexen voor betere prestaties
CREATE INDEX idx_gebruiker_email ON Gebruiker(Email);
CREATE INDEX idx_reservatie_dateres ON Reservatie(Date_Time_res);
CREATE INDEX idx_reservatie_printer ON Reservatie(Printer_ID);
CREATE INDEX idx_reservatie_user ON Reservatie(User_ID);

-- Voeg trigger toe om te controleren of PIN alleen uit cijfers bestaat
DELIMITER //
CREATE TRIGGER check_pin_before_insert 
BEFORE INSERT ON Reservatie
FOR EACH ROW
BEGIN
    IF NEW.Pin NOT REGEXP '^[0-9]{8}$' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'PIN moet exact 8 cijfers bevatten';
    END IF;
END//

CREATE TRIGGER check_pin_before_update
BEFORE UPDATE ON Reservatie
FOR EACH ROW
BEGIN
    IF NEW.Pin NOT REGEXP '^[0-9]{8}$' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'PIN moet exact 8 cijfers bevatten';
    END IF;
END//
DELIMITER ;
