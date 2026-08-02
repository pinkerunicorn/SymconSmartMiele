<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Trait_SmartLog.php';
require_once __DIR__ . '/../libs/Trait_DeviceAvailability.php';

class MieleWasher extends IPSModuleStrict
{
    use SmartLog_Trait;
    use DeviceAvailability_Trait;


    public function Create(): void{
        parent::Create();
        
        
        $this->RegisterPropertyString('DeviceID', '');
        $this->RegisterPropertyBoolean('EnableTwinDos', true);

        $this->DA_RegisterAvailability(900);

        $this->RegisterAttributeInteger('LastTwinDos1', 0);
        $this->RegisterAttributeInteger('LastTwinDos2', 0);
        $this->RegisterAttributeFloat('LastEnergy', 0.0);
        $this->RegisterAttributeFloat('LastWater', 0.0);
        $this->RegisterAttributeInteger('AnchorStartTime', 0);

        // Connect to Splitter

        
        // Variables
        $this->RegisterVariableInteger('PowerSupply', 'Spannungsversorgung', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Power'
        ], 5);

        $this->RegisterVariableBoolean('PowerOn', 'Eingeschaltet', [
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
            'ICON' => 'Power'
        ], 8);
        $this->EnableAction('PowerOn');

        // Aktion als Dropdown
        $processActionPres = [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'ICON' => 'Execute',
            'OPTIONS' => json_encode([
                ['Value' => 0, 'Caption' => 'Keine Aktion', 'IconActive' => false, 'IconValue' => '', 'Color' => -1],
                ['Value' => 1, 'Caption' => 'Start', 'IconActive' => true, 'IconValue' => 'Execute', 'Color' => 0x00CC00],
                ['Value' => 2, 'Caption' => 'Stop', 'IconActive' => true, 'IconValue' => 'Close', 'Color' => 0xFF0000],
                ['Value' => 3, 'Caption' => 'Pause', 'IconActive' => true, 'IconValue' => 'Clock', 'Color' => 0xFFAA00]
            ])
        ];
        $this->RegisterVariableInteger('ProcessAction', 'Aktion', $processActionPres, 9);
        $this->EnableAction('ProcessAction');
        $this->RegisterVariableString('StatusText', 'Status', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Information'
        ], 10);
        $this->RegisterVariableBoolean('SignalInfo', 'Hinweis vorhanden', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Information'
        ], 11);
        $this->RegisterVariableBoolean('SignalFailure', 'Fehler erkannt', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Alert'
        ], 12);
        
        $this->RegisterVariableString('ProgramName', 'Programmbezeichnung', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Script'
        ], 21);
        $this->RegisterVariableString('ProgramPhaseText', 'Programm-Phase', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Script'
        ], 22);
        
        $this->RegisterVariableInteger('ElapsedTime', 'verstrichene Zeit', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => 'min',
            'ICON' => 'Clock'
        ], 27);
        
        $this->RegisterVariableString('StartTime', 'Start um', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Clock'
        ], 23);
        $this->RegisterVariableString('FinishTime', 'Ende um', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Clock'
        ], 24);
        $this->RegisterVariableInteger('RemainingTime', 'verbleibende Zeit', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => 'min',
            'ICON' => 'Clock'
        ], 28);
        $this->RegisterVariableInteger('RemainingTimeSeconds', 'verbleibende Zeit (Sekunden)', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => 's',
            'ICON' => 'Clock'
        ], 29);
        $this->RegisterVariableInteger('ProgressPct', 'Arbeitsfortschritt', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => '%',
            'ICON' => 'Intensity'
        ], 30);
        
        $this->RegisterVariableInteger('Temperature', 'Temperatur', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => '°C',
            'ICON' => 'Temperature'
        ], 31);
        $this->RegisterVariableInteger('SpinSpeed', 'Drehzahl', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => 'U/min',
            'ICON' => 'Motion'
        ], 32);
        $this->RegisterVariableBoolean('Door', 'Tür', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'door-closed'
        ], 33);
        
        $this->RegisterVariableInteger('TwinDos1', 'TwinDos 1 Füllstand', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => '%',
            'ICON' => 'Drop'
        ], 40);
        $this->RegisterVariableInteger('TwinDos2', 'TwinDos 2 Füllstand', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => '%',
            'ICON' => 'Drop'
        ], 45);
        
        $this->RegisterVariableFloat('CurrentWaterConsumption', 'aktueller Wasserverbrauch', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => 'l',
            'ICON' => 'Drop'
        ], 50);
        $this->RegisterVariableFloat('CurrentEnergyConsumption', 'aktueller Energieverbrauch', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => 'kWh',
            'ICON' => 'Electricity'
        ], 55);
        
        $this->RegisterVariableFloat('LastEnergyConsumption', 'letzter Energieverbrauch', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => 'kWh',
            'ICON' => 'Electricity'
        ], 60);
        $this->RegisterVariableFloat('LastWaterConsumption', 'letzter Wasserverbrauch', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => 'l',
            'ICON' => 'Drops'
        ], 61);
    }

    public function ApplyChanges(): void{
        parent::ApplyChanges();

        if (empty($this->ReadPropertyString('DeviceID'))) {
            $this->SetStatus(104);
            return;
        }

        IPS_SetVariableCustomProfile($this->GetIDForIdent('ProcessAction'), '');
        $this->EnableAction('ProcessAction');
        $this->EnableAction('PowerOn');

        // CustomPresentation: Tür
        $doorOptions = json_encode([
            ['Value' => false, 'Caption' => 'Offen', 'IconValue' => 'door-open', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFF0000, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFF0000],
            ['Value' => true, 'Caption' => 'Geschlossen', 'IconValue' => 'door-closed', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0x00FF00, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0x00FF00]
        ]);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Door'), [
            'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
            'ICON' => 'door-closed',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => $doorOptions
        ]);

        // CustomPresentation: PowerSupply
        $powerSupplyIntervals = json_encode([
            [ 'IntervalMinValue' => 0, 'IntervalMaxValue' => 1, 'ConstantActive' => true, 'ConstantValue' => 'Unbekannt', 'ConversionFactor' => 1, 'PrefixActive' => false, 'PrefixValue' => '', 'SuffixActive' => false, 'SuffixValue' => '', 'DigitsActive' => false, 'DigitsValue' => 0, 'IconActive' => true, 'IconValue' => 'Power', 'ColorActive' => true, 'ColorValue' => -1, 'ContentColorActive' => true, 'ContentColorValue' => 0xFFFFFF ],
            [ 'IntervalMinValue' => 1, 'IntervalMaxValue' => 2, 'ConstantActive' => true, 'ConstantValue' => 'Eingeschaltet', 'ConversionFactor' => 1, 'PrefixActive' => false, 'PrefixValue' => '', 'SuffixActive' => false, 'SuffixValue' => '', 'DigitsActive' => false, 'DigitsValue' => 0, 'IconActive' => true, 'IconValue' => 'Power', 'ColorActive' => true, 'ColorValue' => 0x00CC00, 'ContentColorActive' => true, 'ContentColorValue' => 0xFFFFFF ],
            [ 'IntervalMinValue' => 2, 'IntervalMaxValue' => 3, 'ConstantActive' => true, 'ConstantValue' => 'Ausgeschaltet', 'ConversionFactor' => 1, 'PrefixActive' => false, 'PrefixValue' => '', 'SuffixActive' => false, 'SuffixValue' => '', 'DigitsActive' => false, 'DigitsValue' => 0, 'IconActive' => true, 'IconValue' => 'Power', 'ColorActive' => true, 'ColorValue' => 0xFF0000, 'ContentColorActive' => true, 'ContentColorValue' => 0xFFFFFF ]
        ]);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('PowerSupply'), [
            'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
            'ICON' => 'Power',
            'INTERVALS_ACTIVE' => true,
            'INTERVALS' => $powerSupplyIntervals
        ]);
        IPS_SetVariableCustomProfile($this->GetIDForIdent('PowerSupply'), '');
        IPS_SetVariableCustomProfile($this->GetIDForIdent('PowerOn'), '');
        
        // Migration: Delete legacy profiles
        if (IPS_VariableProfileExists('Miele.PowerSupply')) {
            IPS_DeleteVariableProfile('Miele.PowerSupply');
        }
        if (IPS_VariableProfileExists('Miele.PowerOn')) {
            IPS_DeleteVariableProfile('Miele.PowerOn');
        }
        if (IPS_VariableProfileExists('SM.Miele.ProcessAction')) {
            IPS_DeleteVariableProfile('SM.Miele.ProcessAction');
        }

        // CustomPresentation: SignalInfo
        $signalInfoOptions = json_encode([
            ['Value' => false, 'Caption' => 'Kein Hinweis', 'IconValue' => 'Information', 'IconActive' => false, 'ColorActive' => false, 'ColorDisplay' => -1, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => -1],
            ['Value' => true, 'Caption' => 'Hinweis!', 'IconValue' => 'Warning', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFFA500, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFFA500]
        ]);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('SignalInfo'), [
            'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
            'ICON' => 'Information',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => $signalInfoOptions
        ]);

        // CustomPresentation: SignalFailure
        $signalFailureOptions = json_encode([
            ['Value' => false, 'Caption' => 'OK', 'IconValue' => 'Ok', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0x00CC00, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0x00CC00],
            ['Value' => true, 'Caption' => 'Fehler!', 'IconValue' => 'Alert', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFF0000, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFF0000]
        ]);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('SignalFailure'), [
            'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
            'ICON' => 'Alert',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => $signalFailureOptions
        ]);

        $this->DA_ApplyPresentation();
    }

    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        if (!is_array($data)) {
            return "";
        }
        if ($data['DataID'] == '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}') {
            $deviceId = $this->ReadPropertyString('DeviceID');
            if (empty($deviceId)) {
                return "";
            }

            $type = $data['Type'] ?? '';
            if (($type === 'DeviceUpdate' || !isset($data['Type'])) && isset($data['Devices'][$deviceId])) {
                $this->ProcessDeviceData($data['Devices'][$deviceId]);
                $this->DA_SetAvailable(true);
                
                if ($this->ReadPropertyBoolean('EnableTwinDos')) {
                    $this->FetchFillingLevels($deviceId);
                }
            }
            if ($type === 'ActionsUpdate' && isset($data['Actions'][$deviceId])) {
                $this->SetBuffer('DeviceActions', json_encode($data['Actions'][$deviceId]));
            }
        }
    
        return "";
    }

    private function FetchFillingLevels(string $deviceId): void
    {
        $payload = [
            'DataID'=> '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}',
            'Command'=> 'ApiGet',
            'Endpoint'=> '/v1/devices/'. urlencode($deviceId) . '/fillingLevels'
        ];
        
        $result = $this->SendDataToParent(json_encode($payload));
        $fillingLevels = json_decode($result, true);
        
        if ($fillingLevels) {
            $val1 = null;
            if (isset($fillingLevels['twinDosContainer1FillingLevel'])) {
                $val1 = is_array($fillingLevels['twinDosContainer1FillingLevel']) ? $fillingLevels['twinDosContainer1FillingLevel']['value_raw'] : $fillingLevels['twinDosContainer1FillingLevel'];
            }
            if ($val1 === null || $val1 === '') {
                $cached = $this->ReadAttributeInteger('LastTwinDos1');
                if ($cached > 0) {
                    $this->SetValue('TwinDos1', $cached);
                }
            } else {
                $val1 = (int)$val1;
                $this->WriteAttributeInteger('LastTwinDos1', $val1);
                $this->SetValue('TwinDos1', $val1);
            }

            $val2 = null;
            if (isset($fillingLevels['twinDosContainer2FillingLevel'])) {
                $val2 = is_array($fillingLevels['twinDosContainer2FillingLevel']) ? $fillingLevels['twinDosContainer2FillingLevel']['value_raw'] : $fillingLevels['twinDosContainer2FillingLevel'];
            }
            if ($val2 === null || $val2 === '') {
                $cached = $this->ReadAttributeInteger('LastTwinDos2');
                if ($cached > 0) {
                    $this->SetValue('TwinDos2', $cached);
                }
            } else {
                $val2 = (int)$val2;
                $this->WriteAttributeInteger('LastTwinDos2', $val2);
                $this->SetValue('TwinDos2', $val2);
            }
        }
    }


    private function ProcessDeviceData(array $deviceData): void
    {
        if (isset($deviceData['state'])) {
            $state = $deviceData['state'];

            if (isset($state['status']['value_localized'])) {
                $newStatus = (string)$state['status']['value_localized'];
                if (@$this->GetValue('StatusText') !== $newStatus) {
                    $this->SLogInfo("Status geändert: ". $newStatus);
                }
                $this->SetValue('StatusText', $newStatus);
            }

            // Power state: off (1) = false, anything else = true
            if (isset($state['status']['value_raw'])) {
                $this->SetValue('PowerOn', (int)$state['status']['value_raw'] !== 1);
            }
            if (isset($state['powerSupply']['value_raw'])) {
                $this->SetValue('PowerSupply', (int)$state['powerSupply']['value_raw']);
            }
            if (isset($state['signalInfo'])) {
                $this->SetValue('SignalInfo', (bool)$state['signalInfo']);
            }
            if (isset($state['signalFailure'])) {
                $this->SetValue('SignalFailure', (bool)$state['signalFailure']);
            }
            
            if (isset($state['ProgramID']['value_localized'])) {
                $this->SetValue('ProgramName', (string)$state['ProgramID']['value_localized']);
            }
            if (isset($state['programPhase']['value_localized'])) {
                $this->SetValue('ProgramPhaseText', (string)$state['programPhase']['value_localized']);
            }

            if (isset($state['targetTemperature'][0]['value_raw'])) {
                $t = $state['targetTemperature'][0]['value_raw'];
                if ($t > -100) {
                    if ($t >= 1000) {
                        $this->SetValue('Temperature', (int)($t / 100));
                    } else {
                        $this->SetValue('Temperature', (int)$t);
                    }
                }
            }
            if (isset($state['spinningSpeed']['value_raw'])) {
                $s = $state['spinningSpeed']['value_raw'];
                if ($s > -1) $this->SetValue('SpinSpeed', (int)$s);
            }
            if (isset($state['signalDoor'])) {
                $this->SetValue('Door', (bool)$state['signalDoor']);
            }
            
            if (isset($state['ecoFeedback']['currentWaterConsumption']['value'])) {
                $water = (float)$state['ecoFeedback']['currentWaterConsumption']['value'];
                $this->SetValue('CurrentWaterConsumption', $water);
                if ($water > 0) {
                    $this->WriteAttributeFloat('LastWater', $water);
                    $this->SetValue('LastWaterConsumption', $water);
                } else {
                    $this->SetValue('LastWaterConsumption', $this->ReadAttributeFloat('LastWater'));
                }
            }
            if (isset($state['ecoFeedback']['currentEnergyConsumption']['value'])) {
                $energy = (float)$state['ecoFeedback']['currentEnergyConsumption']['value'];
                $this->SetValue('CurrentEnergyConsumption', $energy);
                if ($energy > 0) {
                    $this->WriteAttributeFloat('LastEnergy', $energy);
                    $this->SetValue('LastEnergyConsumption', $energy);
                } else {
                    $this->SetValue('LastEnergyConsumption', $this->ReadAttributeFloat('LastEnergy'));
                }
            }

            $statusRaw = $state['status']['value_raw'] ?? 0;
            
            // --- Time & Progress Calculation ---
            $remMinutes = @$this->GetValue('RemainingTime');
            if (isset($state['remainingTime']) && is_array($state['remainingTime']) && count($state['remainingTime']) == 2) {
                $remMinutes = ($state['remainingTime'][0] * 60) + $state['remainingTime'][1];
            } else if (isset($state['remainingTime']) && is_array($state['remainingTime']) && count($state['remainingTime']) == 0) {
                if ($statusRaw != 5 && $statusRaw != 7) $remMinutes = 0;
            }

            $elapsedMinutes = @$this->GetValue('ElapsedTime');
            if (isset($state['elapsedTime']) && is_array($state['elapsedTime']) && count($state['elapsedTime']) == 2) {
                $elapsedMinutes = ($state['elapsedTime'][0] * 60) + $state['elapsedTime'][1];
            } else if (isset($state['elapsedTime']) && is_array($state['elapsedTime']) && count($state['elapsedTime']) == 0) {
                if ($statusRaw != 5 && $statusRaw != 7) $elapsedMinutes = 0;
            }

            if ($statusRaw == 7) { // Finished
                $remMinutes = 0;
                $progress = 100;
            } else if ($statusRaw == 5) { // In Use
                $now = (int)(floor(time() / 60) * 60); // Strip seconds
                $oldAnchor = $this->ReadAttributeInteger('AnchorStartTime');
                
                $machineElapsed = 0;
                if (isset($state['elapsedTime']) && is_array($state['elapsedTime']) && count($state['elapsedTime']) == 2) {
                    $machineElapsed = ($state['elapsedTime'][0] * 60) + $state['elapsedTime'][1];
                }
                
                if ($machineElapsed > 0) {
                    $elapsedMinutes = $machineElapsed;
                    $expectedStart = $now - ($elapsedMinutes * 60);
                    // Jitter protection: keep anchored StartTime if it's close
                    if ($oldAnchor > 0 && abs($expectedStart - $oldAnchor) < 300) {
                        $anchor = $oldAnchor;
                    } else {
                        $anchor = $expectedStart;
                    }
                } else {
                    // Miele Waschmaschinen senden oft KEINE verstrichene Zeit.
                    // Wir frieren die Startzeit ein und berechnen die verstrichene Zeit selbst!
                    if ($oldAnchor > 0 && $oldAnchor <= time()) {
                        $anchor = $oldAnchor;
                    } else {
                        $anchor = $now;
                    }
                    $elapsedMinutes = (int)round((time() - $anchor) / 60);
                }
                $this->WriteAttributeInteger('AnchorStartTime', $anchor);
                
                $total = $elapsedMinutes + $remMinutes;
                $progress = ($total > 0) ? (int)round(($elapsedMinutes / $total) * 100) : 0;
            } else if ($statusRaw == 4) { // Waiting to start
                $progress = 0;
                $elapsedMinutes = 0;
            } else { // Off, Idle
                $progress = 0;
                $elapsedMinutes = 0;
                $remMinutes = 0;
                $this->WriteAttributeInteger('AnchorStartTime', 0);
            }

            $this->SetValue('ElapsedTime', (int)$elapsedMinutes);
            $this->SetValue('RemainingTime', (int)$remMinutes);
            $this->SetValue('RemainingTimeSeconds', (int)($remMinutes * 60));
            $this->SetValue('ProgressPct', (int)$progress);
            
            // StartTime String
            $startTimeStr = '-';
            if (isset($state['startTime']) && is_array($state['startTime']) && count($state['startTime']) == 2) {
                $startTimeStr = sprintf('%02d:%02d', $state['startTime'][0], $state['startTime'][1]);
            }
            $this->SetValue('StartTime', $startTimeStr);
            
            // FinishTime String
            $finishTimeStr = '-';
            if ($statusRaw == 5 || $statusRaw == 7) {
                if ($remMinutes > 0) {
                    $finishTimeStr = date('H:i', time() + ($remMinutes * 60));
                } else {
                    $finishTimeStr = date('H:i');
                }
            }
            $this->SetValue('FinishTime', $finishTimeStr);
        }
    }

    public function UpdateDevice(): void
    {
        $deviceId = $this->ReadPropertyString('DeviceID');
        if (empty($deviceId)) {
            echo "Fehler: Bitte zuerst eine Device ID eintragen.\n";
            return;
        }

        $payload = [
            'DataID'=> '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}',
            'Command'=> 'ApiGet',
            'Endpoint'=> '/v1/devices/'. urlencode($deviceId) . '/state'
        ];
        
        $result = $this->SendDataToParent(json_encode($payload));
        $state = json_decode($result, true);

        if ($state && is_array($state) && !isset($state['message'])) {
            $this->ProcessDeviceData(['state'=> $state]);
            $this->DA_SetAvailable(true);
            
            if ($this->ReadPropertyBoolean('EnableTwinDos')) {
                $this->FetchFillingLevels($deviceId);
            }
            
            echo "Gerät erfolgreich aktualisiert!\n";
        } else {
            $this->DA_SetAvailable(false, 'API-Fehler beim manuellen Update');
            if (isset($state['message'])) {
                $this->SLog('ERROR', 'Miele Update-Fehler', $state['message'] ?? 'Unbekannter Fehler');
            } else {
                echo "Fehler beim Update: Konnte keine Daten abrufen. Bitte API-Verbindung und Device ID prüfen.\n";
            }
        }
    }


    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'DA_Watchdog':
                $this->DA_HandleWatchdog();
                break;
            case 'PowerOn':
                if ($Value) {
                    $this->SendAction(['powerOn' => true]);
                } else {
                    $this->SendAction(['powerOff' => true]);
                }
                $this->SetValue('PowerOn', (bool)$Value);
                break;
            case 'ProcessAction':
                if ($Value > 0) {
                    $this->SendAction(['processAction' => (int)$Value]);
                }
                $this->SetValue('ProcessAction', (int)$Value);
                break;
        }
    }

    private function SendAction(array $actionData): void
    {
        $deviceId = $this->ReadPropertyString('DeviceID');
        if (empty($deviceId)) {
            $this->SLogInfo("Fehler: Device ID fehlt.");
            return;
        }

        $payload = [
            'DataID' => '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}',
            'Command' => 'ExecuteAction',
            'DeviceID' => $deviceId,
            'ActionData' => $actionData
        ];
        $this->SendDataToParent(json_encode($payload));
    }

    public function GetConfigurationForm(): string
    {
        return <<<'EOT'
{
    "elements": [
        {
            "type": "Label",
            "caption": "Damit ich deine Waschmaschine finde, trag bitte hier die Miele Device ID (fabNumber) ein."
        },
        {
            "type": "RowLayout",
            "items": [
                {
                    "type": "ValidationTextBox",
                    "name": "DeviceID",
                    "caption": "Miele Device ID (fabNumber)"
                },
                {
                    "type": "CheckBox",
                    "name": "EnableTwinDos",
                    "caption": "Enable TwinDos Variables (Level 1 & 2)"
                }
            ]
        },
        {
            "type": "Label",
            "caption": "⚠️ Fernstart (Start/Stop/Pause) funktioniert nur, wenn MobileStart am Gerät physisch aktiviert wurde und die Tür geschlossen ist."
        }
    ],
    "actions": [
        {
            "type": "Button",
            "caption": "Gerät aktualisieren",
            "onClick": "SM_UpdateDevice($id);"
        }
    ],
    "status": [
        {
            "code": 104,
            "icon": "inactive",
            "caption": "Device ID nicht konfiguriert"
        },
        {
            "code": 200,
            "icon": "error",
            "caption": "Fehler beim Datenabruf"
        }
    ]
}
EOT;
    }
}
