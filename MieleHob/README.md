# Miele Hob

Dieses Modul integriert ein Miele Kochfeld in IP-Symcon und zeigt den aktuellen Status des Geräts sowie die Stufen der einzelnen Kochzonen an.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Konfiguration](#4-konfiguration)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [PHP-Befehlsreferenz](#6-php-befehlsreferenz)

### 1. Funktionsumfang

* Anzeige des aktuellen Betriebsstatus des Kochfelds.
* Anzeige der aktuellen Stufe für jede einzelne Kochzone.
* Dynamische Anlage der Variablen für die Kochzonen (abhängig von der Konfiguration, bis zu 6 Kochzonen).

### 2. Voraussetzungen

* IP-Symcon ab Version 9.0
* Eingerichtete "Miele Splitter" Instanz

### 3. Installation

* Über den Module Store das Modul `Miele Hob` installieren.
* Alternativ über das Module Control folgende URL hinzufügen: `https://github.com/pinkerunicorn/SymconSmartMiele`

### 4. Konfiguration

* **Miele Device ID (fabNumber)**: Die eindeutige Identifikationsnummer (fabNumber) des Kochfelds.
* **Anzahl Kochzonen**: Legt fest, wie viele Statusvariablen für die Kochzonen angelegt werden sollen (1 bis 6).

### 5. Statusvariablen und Profile

| Ident | Name | Typ | Beschreibung |
|:---|:---|:---|:---|
| StatusText | Status | String | Der aktuelle Betriebsstatus des Kochfelds |
| Plate1 ... PlateX | Kochzone 1 ... X | String | Die aktuelle Heizstufe der jeweiligen Kochzone (wobei X der konfigurierten Anzahl entspricht) |

### 6. PHP-Befehlsreferenz

```php
SM_UpdateDevice(int $InstanceID);
```
Ruft sofort die neuesten Daten für dieses Kochfeld von der Miele API ab und aktualisiert die Statusvariablen.
