# Miele Washer

Dieses Modul integriert eine Miele Waschmaschine in IP-Symcon und liefert umfangreiche Status- und Programminformationen sowie (falls unterstützt) die Füllstände der TwinDos-Behälter.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Konfiguration](#4-konfiguration)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [PHP-Befehlsreferenz](#6-php-befehlsreferenz)

### 1. Funktionsumfang

* Auslesen des aktuellen Gerätestatus (z.B. Läuft, Fertig).
* Überwachung auf Hinweise und Fehler.
* Anzeige des aktuellen Programms und der Programm-Phase.
* Darstellung der Zeiten (Startzeit, Endzeit, verstrichene und verbleibende Zeit) sowie des Arbeitsfortschritts.
* Anzeige von Temperatur und Schleuderdrehzahl.
* Anzeige des Tür-Status.
* Anzeige von Wasser- und Energieverbrauch.
* Auslesen der Füllstände der TwinDos-Behälter (falls aktiviert und vom Gerät unterstützt).

### 2. Voraussetzungen

* IP-Symcon ab Version 9.0
* Eingerichtete "Miele Splitter" Instanz

### 3. Installation

* Über den Module Store das Modul `Miele Washer` installieren.
* Alternativ über das Module Control folgende URL hinzufügen: `https://github.com/pinkerunicorn/SymconSmartMiele`

### 4. Konfiguration

* **Miele Device ID (fabNumber)**: Die eindeutige Identifikationsnummer (fabNumber) der Waschmaschine.
* **Enable TwinDos Variables (Level 1 & 2)**: Falls aktiviert, werden die Füllstände für TwinDos abgefragt und in Symcon aktualisiert.

### 5. Statusvariablen und Profile

| Ident | Name | Typ | Beschreibung |
|:---|:---|:---|:---|
| StatusText | Status | String | Der aktuelle Betriebsstatus der Waschmaschine |
| SignalInfo | Hinweis vorhanden | Boolean | Gibt an, ob ein Hinweis am Gerät anliegt |
| SignalFailure | Fehler erkannt | Boolean | Gibt an, ob ein Fehler am Gerät aufgetreten ist |
| ProgramName | Programmbezeichnung | String | Der Name des aktuell gewählten Programms |
| ProgramPhaseText | Programm-Phase | String | Die aktuelle Phase im Programm |
| StartTime | Start um | Integer | Die erwartete oder tatsächliche Startzeit |
| FinishTime | Ende um | Integer | Die erwartete Endzeit des Programms |
| ElapsedTime | verstrichene Zeit | Integer | Die bereits verstrichene Programmzeit in Minuten |
| RemainingTime | verbleibende Zeit | Integer | Die verbleibende Programmzeit in Minuten |
| RemainingTimeSeconds | verbleibende Zeit (Sekunden) | Integer | Die verbleibende Programmzeit in Sekunden |
| ProgressPct | Arbeitsfortschritt | Integer | Der Programmfortschritt in Prozent |
| Temperature | Temperatur | Integer | Die Temperatur des gewählten Programms in °C |
| SpinSpeed | Drehzahl | Integer | Die eingestellte Schleuderdrehzahl in U/min |
| Door | Tür | Boolean | Der Status der Gerätetür (offen/geschlossen) |
| TwinDos1 | TwinDos 1 Füllstand | Integer | Der Füllstand von Kartusche 1 in Prozent (nur wenn aktiviert) |
| TwinDos2 | TwinDos 2 Füllstand | Integer | Der Füllstand von Kartusche 2 in Prozent (nur wenn aktiviert) |
| CurrentWaterConsumption | aktueller Wasserverbrauch | Float | Der aktuelle Wasserverbrauch in Liter |
| CurrentEnergyConsumption | aktueller Energieverbrauch | Float | Der aktuelle Energieverbrauch in kWh |

### 6. PHP-Befehlsreferenz

```php
SM_UpdateDevice(int $InstanceID);
```
Ruft sofort die neuesten Daten für diese Waschmaschine von der Miele API ab und aktualisiert die Statusvariablen.
