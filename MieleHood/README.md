# Miele Hood

Dieses Modul integriert eine Miele Dunstabzugshaube in IP-Symcon. Es liest den aktuellen Status aus und ermöglicht die Steuerung von Beleuchtung sowie Lüfterstufe.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Konfiguration](#4-konfiguration)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [PHP-Befehlsreferenz](#6-php-befehlsreferenz)

### 1. Funktionsumfang

* Auslesen des aktuellen Gerätestatus.
* Anzeige und Schalten der Beleuchtung.
* Anzeige und Einstellung der Lüfterstufe (0 bis 4).

### 2. Voraussetzungen

* IP-Symcon ab Version 9.0
* Eingerichtete "Miele Splitter" Instanz

### 3. Installation

* Über den Module Store das Modul `Miele Hood` installieren.
* Alternativ über das Module Control folgende URL hinzufügen: `https://github.com/pinkerunicorn/SymconSmartMiele`

### 4. Konfiguration

* **Miele Device ID (fabNumber)**: Die eindeutige Identifikationsnummer (fabNumber) der Dunstabzugshaube.

### 5. Statusvariablen und Profile

| Ident | Name | Typ | Beschreibung |
|:---|:---|:---|:---|
| StatusText | Status | String | Der aktuelle Betriebsstatus der Dunstabzugshaube |
| Light | Licht | Boolean | Status der Beleuchtung (schaltbar) |
| VentilationStep | Lüfterstufe | Integer | Die aktuell eingestellte Lüfterstufe (schaltbar) |

### 6. PHP-Befehlsreferenz

```php
SM_UpdateDevice(int $InstanceID);
```
Ruft sofort die neuesten Daten für diese Dunstabzugshaube von der Miele API ab und aktualisiert die Statusvariablen.
