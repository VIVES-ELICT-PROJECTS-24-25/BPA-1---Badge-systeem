-- Tabel OPOs
CREATE TABLE OPOs (
    id INT PRIMARY KEY,
    opleiding_id INT,
    naam VARCHAR(255)
);

-- Tabel opleidingen
CREATE TABLE opleidingen (
    id INT PRIMARY KEY,
    naam VARCHAR(255)
);

-- Tabel kostenbewijzing_onderzoekers
CREATE TABLE kostenbewijzing_onderzoekers (
    reservatie_id INT PRIMARY KEY,
    onderzoeksproject VARCHAR(255),
    kostenpost VARCHAR(255)
);

-- Tabel kostenbewijzing_studenten
CREATE TABLE kostenbewijzing_studenten (
    reservatie_id INT PRIMARY KEY,
    OPO_id INT,
    eigen_rekening BOOLEAN
);

-- Tabel User
CREATE TABLE User (
    User_ID INT PRIMARY KEY,
    Voornaam VARCHAR(255),
    Naam VARCHAR(255),
    Emailadres VARCHAR(255),
    Telefoon VARCHAR(50),
    Wachtwoord VARCHAR(255),
    Type ENUM('student', 'onderzoeker', 'beheerder'),
    AanmaakAccount DATETIME,
    LaatsteAanmelding DATETIME,
    HuidigActief BOOLEAN
);

-- Tabel Vives
CREATE TABLE Vives (
    User_ID INT PRIMARY KEY,
    Voornaam VARCHAR(255),
    Vives_id VARCHAR(50),
    opleiding_id INT,
    Type ENUM('student', 'medewerker', 'onderzoeker'),
    FOREIGN KEY (User_ID) REFERENCES User(User_ID),
    FOREIGN KEY (opleiding_id) REFERENCES opleidingen(id)
);

-- Tabel Printer
CREATE TABLE Printer (
    Printer_ID INT PRIMARY KEY,
    Status ENUM('beschikbaar', 'in_gebruik', 'onderhoud', 'defect'),
    LAATSTE_STATUS_CHANGE DATETIME,
    netwerkadres VARCHAR(255),
    Versie_Toestel VARCHAR(100),
    Software ENUM('versie1', 'versie2', 'versie3'),
    Datadrager ENUM('SD', 'USB', 'WIFI'),
    Bouwvolume_id INT,
    Opmerkingen TEXT
);

-- Tabel bouwvolume
CREATE TABLE bouwvolume (
    id INT PRIMARY KEY,
    lengte INT,
    breedte INT,
    hoogte INT
);

-- Tabel Lokalen
CREATE TABLE Lokalen (
    id INT PRIMARY KEY,
    Locatie VARCHAR(255)
);

-- Tabel Openingsuren
CREATE TABLE Openingsuren (
    id INT PRIMARY KEY,
    Lokaal_id INT,
    Tijdstip_start DATETIME,
    Tijdstip_einde DATETIME,
    FOREIGN KEY (Lokaal_id) REFERENCES Lokalen(id)
);

-- Tabel Filament
CREATE TABLE Filament (
    id INT PRIMARY KEY,
    Type ENUM('PLA', 'ABS', 'PETG', 'TPU', 'Nylon'),
    Kleur ENUM('rood', 'blauw', 'groen', 'zwart', 'wit', 'geel', 'transparant')
);

-- Tabel Filament_compatibiliteit
CREATE TABLE Filament_compatibiliteit (
    printer_id INT,
    filament_id INT,
    PRIMARY KEY (printer_id, filament_id),
    FOREIGN KEY (printer_id) REFERENCES Printer(Printer_ID),
    FOREIGN KEY (filament_id) REFERENCES Filament(id)
);

-- Tabel Reservatie
CREATE TABLE Reservatie (
    Reservatie_ID INT PRIMARY KEY,
    User_ID INT,
    Printer_ID INT,
    DATE_TIME_RESERVATIE DATETIME,
    PRINT_START DATETIME,
    PRINT_END DATETIME,
    Comment TEXT,
    Pincode VARCHAR(10),
    filament_id INT,
    verbruik FLOAT,
    FOREIGN KEY (User_ID) REFERENCES User(User_ID),
    FOREIGN KEY (Printer_ID) REFERENCES Printer(Printer_ID),
    FOREIGN KEY (filament_id) REFERENCES Filament(id)
);

-- Foreign key relaties toevoegen
ALTER TABLE OPOs
ADD FOREIGN KEY (opleiding_id) REFERENCES opleidingen(id);

ALTER TABLE kostenbewijzing_onderzoekers
ADD FOREIGN KEY (reservatie_id) REFERENCES Reservatie(Reservatie_ID);

ALTER TABLE kostenbewijzing_studenten
ADD FOREIGN KEY (reservatie_id) REFERENCES Reservatie(Reservatie_ID),
ADD FOREIGN KEY (OPO_id) REFERENCES OPOs(id);

ALTER TABLE Printer
ADD FOREIGN KEY (Bouwvolume_id) REFERENCES bouwvolume(id);
