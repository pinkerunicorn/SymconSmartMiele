# Miele Fridge

Dieses Modul integriert einen Miele Kühlschrank in IP-Symcon, liest Temperaturen und Türstatus aus und ermöglicht die Steuerung der Zieltemperatur sowie der Schnellkühl-Funktion.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Konfiguration](#4-konfiguration)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [PHP-Befehlsreferenz](#6-php-befehlsreferenz)

### 1. Funktionsumfang

* Auslesen des aktuellen Gerätestatus.
* Anzeige der Ist-Temperatur (Zone 1).
* Anzeige und Einstellung der Ziel-Temperatur (Zone 1).
* Überwachung des Türstatus (offen/geschlossen).
* Anzeige sowie Ein- und Ausschalten der Schnellkühl-Funktion (SuperCooling).

### 2. Voraussetzungen

* IP-Symcon ab Version 9.0
* Eingerichtete "Miele Splitter" Instanz

### 3. Installation

* Über den Module Store das Modul `Miele Fridge` installieren.
* Alternativ über das Module Control folgende URL hinzufügen: `https://github.com/pinkerunicorn/SymconSmartMiele`

### 4. Konfiguration

* **Miele Device ID (fabNumber)**: Die eindeutige Identifikationsnummer (fabNumber) des Kühlschranks.

### 5. Statusvariablen und Profile

| Ident | Name | Typ | Beschreibung |
|:---|:---|:---|:---|
| StatusText | Status | String | Der aktuelle Betriebsstatus des Kühlschranks |
| Temp1 | Ist-Temperatur (Zone 1) | Integer | Die aktuelle Temperatur der Hauptzone in °C |
| TargetTemp1 | Ziel-Temperatur (Zone 1) | Integer | Die eingestellte Ziel-Temperatur der Hauptzone in °C (schaltbar) |
| DoorOpen | Tür geöffnet | Boolean | Gibt an, ob die Kühlschranktür geöffnet ist |
| SuperCooling | Schnellkühlen | Boolean | Status der Schnellkühl-Funktion (schaltbar) |

### 6. PHP-Befehlsreferenz

```php
SM_UpdateDevice(int $InstanceID);
```
Ruft sofort die neuesten Daten für diesen Kühlschrank von der Miele API ab und aktualisiert die Statusvariablen.
