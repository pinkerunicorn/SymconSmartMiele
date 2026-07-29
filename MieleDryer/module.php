<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Trait_SmartLog.php';

class MieleDryer extends IPSModuleStrict
{
    use SmartLog_Trait;


    public function Create(): void{
        parent::Create();
        
        
        // Self-healing for corrupted CustomPresentations
        foreach (@IPS_GetChildrenIDs($this->InstanceID) as $childID) {
            if (@IPS_VariableExists($childID)) {
                @IPS_SetVariableCustomPresentation($childID, []);
            }
        }
        $this->RegisterPropertyString('DeviceID', '');

        $this->RegisterAttributeFloat('LastEnergy', 0.0);
        $this->RegisterAttributeInteger('AnchorStartTime', 0);

        // Variables
        $this->RegisterVariableString('PowerSupply', 'Spannungsversorgung', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Power'
        ], 5);

        $this->RegisterVariableBoolean('PowerOn', 'Eingeschaltet', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Power'
        ], 8);
        $this->EnableAction('PowerOn');

        // Aktion als Dropdown (Profil wird ggf. von MieleWasher miterstellt)
        $this->RegisterVariableInteger('ProcessAction', 'Aktion', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Execute'
        ], 9);
        $this->EnableAction('ProcessAction');

        $this->RegisterVariableString('StatusText', 'Status', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Information'
        ], 10);
        $this->RegisterVariableBoolean('SignalInfo', 'Hinweis vorhanden', [
            'ICON' => 'Information'
        ], 11);
        $this->RegisterVariableBoolean('SignalFailure', 'Fehler erkannt', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Alert'
        ], 12);
        
        $this->RegisterVariableString('ProgramName', 'Programmbezeichnung', [
            'ICON' => 'Script'
        ], 21);
        $this->RegisterVariableString('ProgramPhaseText', 'Programm-Phase', [
            'ICON' => 'Script'
        ], 22);
        
        $this->RegisterVariableInteger('ElapsedTime', 'verstrichene Zeit', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => 'min',
            'ICON' => 'Clock'
        ], 22);
        
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
        ], 28);
        $this->RegisterVariableInteger('ProgressPct', 'Arbeitsfortschritt', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => '%',
            'ICON' => 'Intensity'
        ], 29);
        
        $this->RegisterVariableBoolean('Door', 'Tür', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'door-closed'
        ], 33);

        $this->RegisterVariableString('DrynessLevel', 'Trocknungsstufe', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Drops'
        ], 35);
        
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
    }

    public function ApplyChanges(): void{
        parent::ApplyChanges();

        // CustomPresentation: Aktion Dropdown
        $actionOptions = json_encode([
            ['Value' => 0, 'Caption' => 'Keine Aktion', 'IconValue' => '', 'IconActive' => false, 'ColorActive' => false, 'ColorDisplay' => -1, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => -1],
            ['Value' => 1, 'Caption' => 'Start', 'IconValue' => 'Execute', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0x00CC00, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0x00CC00],
            ['Value' => 2, 'Caption' => 'Stop', 'IconValue' => 'Close', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFF0000, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFF0000],
            ['Value' => 3, 'Caption' => 'Pause', 'IconValue' => 'Clock', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFFAA00, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFFAA00]
        ]);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('ProcessAction'), [
            'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
            'ICON' => 'Execute',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => $actionOptions
        ]);

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

            if ($type === 'DeviceUpdate' || $type === '') {
                if (isset($data['Devices'][$deviceId])) {
                    $this->ProcessDeviceData($data['Devices'][$deviceId]);
                }
            }
            if ($type === 'ActionsUpdate' || $type === '') {
                if (isset($data['Actions'][$deviceId])) {
                    $this->SetBuffer('ActionsUpdate', json_encode($data['Actions'][$deviceId]));
                }
            }
        }
    
        return "";
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'PowerOn':
                $action = $Value ? 'powerOn' : 'powerOff';
                $this->SendAction([$action => true]);
                $this->SetValue($Ident, $Value);
                break;
            case 'ProcessAction':
                if ($Value == 1) {
                    $this->SendAction(['processAction' => 1]);
                } elseif ($Value == 2) {
                    $this->SendAction(['processAction' => 2]);
                } elseif ($Value == 3) {
                    $this->SendAction(['processAction' => 3]);
                }
                $this->SetValue($Ident, 0);
                break;
        }
    }

    private function SendAction(array $actionData): void
    {
        $deviceId = $this->ReadPropertyString('DeviceID');
        if (empty($deviceId)) {
            $this->Log("Fehler: DeviceID ist leer.");
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

    protected function Log(string $text): void
    {
        $this->SLog('INFO', $text);
    }

    private function ProcessDeviceData(array $deviceData): void
    {
        if (isset($deviceData['state'])) {
            $state = $deviceData['state'];

            if (isset($state['status']['value_localized'])) {
                $newStatus = (string)$state['status']['value_localized'];
                if (@$this->GetValue('StatusText') !== $newStatus) {
                    $this->Log("Status geändert: ". $newStatus);
                }
                $this->SetValue('StatusText', $newStatus);
            }
            
            $statusRaw = $state['status']['value_raw'] ?? 0;
            if ($statusRaw > 0) {
                $this->SetValue('PowerOn', $statusRaw != 1);
            }
            if (isset($state['powerSupply']['value_raw'])) {
                $psMap = [0 => 'Unbekannt', 1 => 'Eingeschaltet', 2 => 'Ausgeschaltet'];
                $psRaw = (int)($state['powerSupply']['value_raw'] ?? 0);
                $this->SetValue('PowerSupply', $psMap[$psRaw] ?? 'Unbekannt');
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

            if (isset($state['signalDoor'])) {
                $this->SetValue('Door', (bool)$state['signalDoor']);
            }

            if (isset($state['dryingStep']['value_localized'])) {
                $this->SetValue('DrynessLevel', (string)$state['dryingStep']['value_localized']);
            } elseif (isset($state['drynessLevel']['value_localized'])) {
                $this->SetValue('DrynessLevel', (string)$state['drynessLevel']['value_localized']);
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
                    // Falls der Trockner keine ElapsedTime schickt, berechnen wir sie selbst
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
            echo "Gerät erfolgreich aktualisiert!\n";
        } else {
            if (isset($state['message'])) {
                $this->SLog('ERROR', 'Miele Update-Fehler', $state['message'] ?? 'Unbekannter Fehler');
            } else {
                echo "Fehler beim Update: Konnte keine Daten abrufen. Bitte API-Verbindung und Device ID prüfen.\n";
            }
        }
    }

    protected function LogMessage(string $Message, int $Type): bool
    {
        $this->SLog('INFO', $Message);
        IPS_LogMessage('SmartVillaKunterbunt', 'MieleDryer: '. $Message);
        return true;
    }

    public function GetConfigurationForm(): string
    {
        return <<<'EOT'
{
    "elements": [
        {
            "type": "Label",
            "caption": "Damit ich deinen Trockner finde, trag bitte hier die Miele Device ID (fabNumber) ein."
        },
        {
            "type": "Label",
            "caption": "Damit eine Steuerung (z.B. Starten, Programmwahl) über IP-Symcon möglich ist, muss \"MobileStart\" am Gerät aktiv sein."
        },
        {
            "type": "RowLayout",
            "items": [
                {
                    "type": "ValidationTextBox",
                    "name": "DeviceID",
                    "caption": "Miele Device ID (fabNumber)"
                }
            ]
        }
    ],
    "actions": [
        {
            "type": "Button",
            "caption": "Gerät aktualisieren",
            "onClick": "SM_UpdateDevice($id);"
        }
    ]
}
EOT;
    }
}
