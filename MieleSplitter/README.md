# Miele Splitter

Der Miele Splitter ist ein Typ-2-Splitter-Modul, das die OAuth2-Authentifizierung gegenüber der Miele Cloud API verwaltet und die Kommunikation mit den Miele-Geräten ermöglicht.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Konfiguration](#4-konfiguration)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [PHP-Befehlsreferenz](#6-php-befehlsreferenz)

### 1. Funktionsumfang

* Verwaltung der OAuth2-Authentifizierung bei der Miele API.
* Regelmäßiges Abrufen der Gerätedaten der verbundenen Miele-Geräte in einem konfigurierbaren Intervall.
* Weiterleitung von Statusänderungen und Daten an untergeordnete Miele-Gerätemodule.
* Ausführung von Aktionen an Miele-Geräten über die Miele Cloud.

### 2. Voraussetzungen

* IP-Symcon ab Version 9.0

### 3. Installation

* Über den Module Store das Modul `Miele Splitter` installieren.
* Alternativ über das Module Control folgende URL hinzufügen: `https://github.com/pinkerunicorn/SymconSmartMiele`

### 4. Konfiguration

* **Client ID**: Die Client-ID für die Miele API.
* **Client Secret**: Das Client-Secret für die Miele API.
* **Miele Username (Email)**: Der Benutzername bzw. die E-Mail-Adresse des Miele-Kontos.
* **Miele Password**: Das Passwort für das Miele-Konto.
* **Country**: Das Land (z.B. Deutschland, Österreich, Schweiz), in dem das Miele-Konto registriert ist.
* **Update Interval (seconds)**: Das Intervall in Sekunden, in dem neue Daten von der API abgerufen werden sollen (Standard: 60 Sekunden).

### 5. Statusvariablen und Profile

Dieses Modul legt keine eigenen Statusvariablen an, sondern leitet die Gerätedaten an die Gerätemodule weiter.

### 6. PHP-Befehlsreferenz

```php
SM_TestConnection(int $InstanceID);
```
Testet die Verbindung zur Miele API und führt eine Authentifizierung durch.

```php
SM_FetchData(int $InstanceID);
```
Ruft manuell die neuesten Gerätedaten von der Miele API ab und verteilt diese an die untergeordneten Instanzen.

```php
SM_ApiGet(int $InstanceID, string $endpoint);
```
Führt einen GET-Request auf den angegebenen Endpunkt der Miele API aus und gibt das Ergebnis zurück.

```php
SM_ExecuteAction(int $InstanceID, string $deviceId, array $actionData);
```
Führt eine Aktion auf dem angegebenen Gerät aus.
