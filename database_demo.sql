-- ============================================================================
-- PHPMacherNG - Demo Datenbank (CRM & Shop Verwaltung)
-- Kompatibel mit MySQL 5.5+ und MariaDB 10.x+
-- ============================================================================

DROP VIEW IF EXISTS `v_bestellungen_uebersicht`;
DROP TABLE IF EXISTS `bestellungen`;
DROP TABLE IF EXISTS `produkte`;
DROP TABLE IF EXISTS `kategorien`;
DROP TABLE IF EXISTS `kunden`;
DROP TABLE IF EXISTS `benutzer`;
DROP TABLE IF EXISTS `userlevelpermissions`;
DROP TABLE IF EXISTS `userlevels`;

CREATE TABLE `userlevels` (
  `userlevelid` int(11) NOT NULL COMMENT 'Rollen-ID',
  `userlevelname` varchar(80) NOT NULL COMMENT 'Rollen-Bezeichnung',
  PRIMARY KEY (`userlevelid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `userlevels` (`userlevelid`, `userlevelname`) VALUES
(-2, 'Anonymous'),
(-1, 'Administrator'),
(0, 'Default (Sachbearbeiter)'),
(1, 'Vertriebsleitung (Manager)'),
(2, 'Buchhaltung (Nur Lesen)');

CREATE TABLE `userlevelpermissions` (
  `userlevelid` int(11) NOT NULL COMMENT 'Rollen-ID',
  `tablename` varchar(190) NOT NULL COMMENT 'Tabellenname',
  `permission` int(11) NOT NULL DEFAULT '0' COMMENT 'Rechte-Bitmaske (Add=1,Del=2,Edit=4,List=8,Admin=16,View=32,Search=64)',
  PRIMARY KEY (`userlevelid`, `tablename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `userlevelpermissions` (`userlevelid`, `tablename`, `permission`) VALUES
(-2, 'kategorien', 0), (-2, 'produkte', 0), (-2, 'kunden', 0), (-2, 'bestellungen', 0), (-2, 'v_bestellungen_uebersicht', 0), (-2, 'benutzer', 0), (-2, 'userlevels', 0), (-2, 'userlevelpermissions', 0),
(-1, 'kategorien', 127), (-1, 'produkte', 127), (-1, 'kunden', 127), (-1, 'bestellungen', 127), (-1, 'v_bestellungen_uebersicht', 127), (-1, 'benutzer', 127), (-1, 'userlevels', 127), (-1, 'userlevelpermissions', 127),
(0, 'kategorien', 104), (0, 'produkte', 104), (0, 'kunden', 109), (0, 'bestellungen', 109), (0, 'v_bestellungen_uebersicht', 104), (0, 'benutzer', 0), (0, 'userlevels', 0), (0, 'userlevelpermissions', 0),
(1, 'kategorien', 111), (1, 'produkte', 111), (1, 'kunden', 111), (1, 'bestellungen', 111), (1, 'v_bestellungen_uebersicht', 104), (1, 'benutzer', 104), (1, 'userlevels', 0), (1, 'userlevelpermissions', 0),
(2, 'kategorien', 104), (2, 'produkte', 104), (2, 'kunden', 104), (2, 'bestellungen', 104), (2, 'v_bestellungen_uebersicht', 104), (2, 'benutzer', 0), (2, 'userlevels', 0), (2, 'userlevelpermissions', 0);

CREATE TABLE `benutzer` (
  `benutzer_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'User-ID',
  `benutzername` varchar(80) NOT NULL COMMENT 'Benutzername (Login)',
  `passwort` varchar(255) NOT NULL COMMENT 'Passwort',
  `anzeigename` varchar(120) NOT NULL COMMENT 'Vollständiger Name',
  `email` varchar(120) DEFAULT NULL COMMENT 'E-Mail',
  `userlevel_id` int(11) NOT NULL DEFAULT '0' COMMENT 'Benutzerrolle (User Level)',
  `vorgesetzter_id` int(11) DEFAULT NULL COMMENT 'Vorgesetzter (Parent User ID)',
  `aktiv` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Aktiv',
  PRIMARY KEY (`benutzer_id`),
  UNIQUE KEY `idx_benutzername` (`benutzername`),
  KEY `idx_userlevel` (`userlevel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `benutzer` (`benutzer_id`, `benutzername`, `passwort`, `anzeigename`, `email`, `userlevel_id`, `vorgesetzter_id`, `aktiv`) VALUES
(1, 'cheffe', 'admin123', 'Dr. Thomas Geschäftsführer (Admin)', 'geschaeftsfuehrung@example.com', -1, NULL, 1),
(2, 'leitung', 'manager123', 'Sandra Vertriebsleitung (Manager)', 's.leitung@example.com', 1, 1, 1),
(3, 'mitarbeiter1', 'user123', 'Michael Sachbearbeiter Nord', 'm.nord@example.com', 0, 2, 1),
(4, 'mitarbeiter2', 'user123', 'Lisa Sachbearbeiterin Süd', 'l.sued@example.com', 0, 2, 1),
(5, 'gast', 'gast123', 'Petra Prüferin (Buchhaltung / Nur Lesen)', 'p.pruefer@example.com', 2, 1, 1);

CREATE TABLE `kategorien` (
  `kategorie_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `bezeichnung` varchar(100) NOT NULL COMMENT 'Kategorie-Name',
  `beschreibung` text COMMENT 'Beschreibung',
  `aktiv` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Aktiv',
  PRIMARY KEY (`kategorie_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `kategorien` (`kategorie_id`, `bezeichnung`, `beschreibung`, `aktiv`) VALUES
(1, 'Notebooks & PCs', 'Tragbare Computer, Workstations und Desktop-Systeme', 1),
(2, 'Monitore & Displays', 'Hochauflösende Office- und Grafik-Monitore', 1),
(3, 'Netzwerk & Server', 'Switches, Router, NAS-Systeme und Zubehör', 1),
(4, 'Software & Lizenzen', 'Entwickler-Tools, Betriebssysteme und Office-Pakete', 1);

CREATE TABLE `produkte` (
  `produkt_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Art.-Nr.',
  `kategorie_id` int(11) NOT NULL COMMENT 'Kategorie',
  `artikelnummer` varchar(40) NOT NULL COMMENT 'SKU / Code',
  `produktname` varchar(150) NOT NULL COMMENT 'Produktname',
  `beschreibung` text COMMENT 'Details',
  `preis` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Einzelpreis',
  `lagerbestand` int(11) NOT NULL DEFAULT '0' COMMENT 'Lagerbestand',
  `lieferbar` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Sofort lieferbar',
  `erstellt_am` date DEFAULT NULL COMMENT 'Aufgenommen am',
  PRIMARY KEY (`produkt_id`),
  KEY `idx_kategorie` (`kategorie_id`),
  CONSTRAINT `fk_produkte_kategorie` FOREIGN KEY (`kategorie_id`) REFERENCES `kategorien` (`kategorie_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `produkte` (`produkt_id`, `kategorie_id`, `artikelnummer`, `produktname`, `beschreibung`, `preis`, `lagerbestand`, `lieferbar`, `erstellt_am`) VALUES
(1, 1, 'NB-PRO-15', 'Workstation ProBook 15"', 'Intel Core i7, 32 GB RAM, 1 TB NVMe SSD, mattes IPS-Display', 1499.00, 14, 1, '2026-01-15'),
(2, 1, 'PC-DEV-X1', 'Developer Mini-Tower X1', 'Leiser Desktop-PC für Softwareentwicklung, 64 GB RAM', 1850.50, 6, 1, '2026-02-10'),
(3, 2, 'MON-4K-27', 'UltraSharp 27" 4K Monitor', 'USB-C Dock integriert, 99% sRGB, höhenverstellbar', 489.90, 28, 1, '2026-03-01'),
(4, 3, 'NAS-4BAY', 'ProNAS 4-Bay Storage Server', '2.5 GbE Dual-LAN, RAID 5/6 Unterstützung, inkl. 16 TB', 799.00, 4, 1, '2026-03-20'),
(5, 4, 'SW-PHP-18', 'PHPMacherNG Enterprise Lizenz', 'Unbegrenzte Projekte, inkl. 12 Monate Wartung', 249.00, 999, 1, '2026-04-05'),
(6, 2, 'MON-UW-34', 'Curved UltraWide 34" Display', '3440x1440 Auflösung, 120 Hz, perfekt für Multitasking', 649.00, 0, 0, '2026-05-12');

CREATE TABLE `kunden` (
  `kunde_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Kunden-Nr.',
  `firma` varchar(120) NOT NULL COMMENT 'Firma / Organisation',
  `ansprechpartner` varchar(100) NOT NULL COMMENT 'Ansprechpartner',
  `email` varchar(120) NOT NULL COMMENT 'E-Mail',
  `telefon` varchar(50) DEFAULT NULL COMMENT 'Telefon',
  `stadt` varchar(80) NOT NULL COMMENT 'Stadt',
  `kundengruppe` enum('Standard','Business','VIP','Behoerde') NOT NULL DEFAULT 'Standard' COMMENT 'Kundengruppe',
  `aktiv` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Aktiv',
  PRIMARY KEY (`kunde_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `kunden` (`kunde_id`, `firma`, `ansprechpartner`, `email`, `telefon`, `stadt`, `kundengruppe`, `aktiv`) VALUES
(1, 'TechSolutions GmbH', 'Markus Weber', 'm.weber@techsolutions.example.com', '+49 89 123456-0', 'München', 'VIP', 1),
(2, 'NordDigital AG', 'Julia Brandt', 'j.brandt@norddigital.example.com', '+49 40 987654-12', 'Hamburg', 'Business', 1),
(3, 'KreativStudio Berlin', 'Lukas Meyer', 'kontakt@kreativstudio.example.com', '+49 30 5554433', 'Berlin', 'Standard', 1),
(4, 'Stadtwerke Rhein-Main', 'Sabine Hoffmann', 's.hoffmann@stadtwerke.example.com', '+49 69 443322-9', 'Frankfurt', 'Behoerde', 1),
(5, 'AlpenSoft KG', 'Andreas Gruber', 'info@alpensoft.example.com', '+43 512 778899', 'Innsbruck', 'Business', 0);

CREATE TABLE `bestellungen` (
  `bestellung_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Bestell-Nr.',
  `kunde_id` int(11) NOT NULL COMMENT 'Kunde',
  `produkt_id` int(11) NOT NULL COMMENT 'Produkt',
  `menge` int(11) NOT NULL DEFAULT '1' COMMENT 'Menge',
  `gesamtbetrag` decimal(10,2) NOT NULL COMMENT 'Gesamtbetrag',
  `status` enum('Offen','In Bearbeitung','Versendet','Abgeschlossen','Storniert') NOT NULL DEFAULT 'Offen' COMMENT 'Bestellstatus',
  `bestelldatum` date NOT NULL COMMENT 'Bestelldatum',
  `bemerkung` text COMMENT 'Interne Notiz',
  `erstellt_von` int(11) DEFAULT '3' COMMENT 'Sachbearbeiter (User ID)',
  `unterschrift_kunde` longtext COMMENT 'Unterschrift Kunde (Touch)',
  PRIMARY KEY (`bestellung_id`),
  KEY `idx_kunde` (`kunde_id`),
  KEY `idx_produkt` (`produkt_id`),
  CONSTRAINT `fk_bestellung_kunde` FOREIGN KEY (`kunde_id`) REFERENCES `kunden` (`kunde_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bestellung_produkt` FOREIGN KEY (`produkt_id`) REFERENCES `produkte` (`produkt_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `bestellungen` (`bestellung_id`, `kunde_id`, `produkt_id`, `menge`, `gesamtbetrag`, `status`, `bestelldatum`, `bemerkung`, `erstellt_von`) VALUES
(1001, 1, 1, 2, 2998.00, 'Abgeschlossen', '2026-09-10', 'Express-Lieferung an Zentrale München', 3),
(1002, 2, 3, 4, 1959.60, 'Versendet', '2026-09-18', 'Tracking-Nr. DHL-99887766', 3),
(1003, 4, 4, 1, 799.00, 'In Bearbeitung', '2026-10-02', 'Inkl. Vorab-Konfiguration RAID 5', 4),
(1004, 3, 5, 3, 747.00, 'Offen', '2026-10-15', 'Lizenzschlüssel per E-Mail senden', 4),
(1005, 1, 2, 1, 1850.50, 'Offen', '2026-10-17', 'Nachbestellung Entwicklungsabteilung', 2);

CREATE VIEW `v_bestellungen_uebersicht` AS
SELECT
  b.bestellung_id,
  b.bestelldatum,
  k.firma AS kunde_firma,
  k.stadt AS kunde_stadt,
  p.artikelnummer,
  p.produktname,
  b.menge,
  b.gesamtbetrag,
  b.status
FROM bestellungen b
JOIN kunden k ON b.kunde_id = k.kunde_id
JOIN produkte p ON b.produkt_id = p.produkt_id;
