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

        // Variables
        $this->RegisterVariableBoolean('PowerOn', 'Eingeschaltet', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Power'
        ], 8);
        $this->EnableAction('PowerOn');

        // Aktion als Dropdown (Profil wird ggf. von MieleWasher miterstellt)
        if (!IPS_VariableProfileExists('SM.Miele.ProcessAction')) {
            IPS_CreateVariableProfile('SM.Miele.ProcessAction', 1);
            IPS_SetVariableProfileAssociation('SM.Miele.ProcessAction', 0, 'Keine Aktion', '', -1);
            IPS_SetVariableProfileAssociation('SM.Miele.ProcessAction', 1, 'Start', 'Execute', 0x00CC00);
            IPS_SetVariableProfileAssociation('SM.Miele.ProcessAction', 2, 'Stop', 'Close', 0xFF0000);
            IPS_SetVariableProfileAssociation('SM.Miele.ProcessAction', 3, 'Pause', 'Clock', 0xFFAA00);
        }
        $this->RegisterVariableInteger('ProcessAction', 'Aktion', 'SM.Miele.ProcessAction', 9);
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
        
        $this->RegisterVariableInteger('StartTime', 'Start um', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Clock'
        ], 25);
        $this->RegisterVariableInteger('FinishTime', 'Ende um', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Clock'
        ], 26);
        $this->RegisterVariableInteger('ElapsedTime', 'verstrichene Zeit', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => 'min',
            'ICON' => 'Clock'
        ], 27);
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
            'ICON' => 'Window'
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
    }

    public function ApplyChanges(): void{
        parent::ApplyChanges();
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
                $this->SetValue('CurrentEnergyConsumption', (float)$state['ecoFeedback']['currentEnergyConsumption']['value']);
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
                $startTime = @$this->GetValue('StartTime');
                $finishTime = @$this->GetValue('FinishTime');
            } else if ($statusRaw == 5) { // In Use
                $now = (int)(floor(time() / 60) * 60); // Strip seconds
                $oldStart = @$this->GetValue('StartTime');
                
                $machineElapsed = 0;
                if (isset($state['elapsedTime']) && is_array($state['elapsedTime']) && count($state['elapsedTime']) == 2) {
                    $machineElapsed = ($state['elapsedTime'][0] * 60) + $state['elapsedTime'][1];
                }
                
                if ($machineElapsed > 0) {
                    $elapsedMinutes = $machineElapsed;
                    $expectedStart = $now - ($elapsedMinutes * 60);
                    // Jitter protection: keep anchored StartTime if it's close
                    if ($oldStart > 0 && abs($expectedStart - $oldStart) < 300) {
                        $startTime = $oldStart;
                    } else {
                        $startTime = $expectedStart;
                    }
                } else {
                    // Falls der Trockner keine ElapsedTime schickt, berechnen wir sie selbst
                    if ($oldStart > 0 && $oldStart <= time()) {
                        $startTime = $oldStart;
                    } else {
                        $startTime = $now;
                    }
                    $elapsedMinutes = (int)round((time() - $startTime) / 60);
                }
                
                $finishTime = $now + ($remMinutes * 60);
                
                $total = $elapsedMinutes + $remMinutes;
                $progress = ($total > 0) ? (int)round(($elapsedMinutes / $total) * 100) : 0;
            } else if ($statusRaw == 4) { // Waiting to start
                $progress = 0;
                $elapsedMinutes = 0;
                if (isset($state['startTime']) && is_array($state['startTime']) && count($state['startTime']) == 2) {
                    $ts = mktime((int)$state['startTime'][0], (int)$state['startTime'][1], 0);
                    if ($ts < time() - (12 * 3600)) $ts += 86400; // Next day
                    $startTime = $ts;
                } else {
                    $startTime = 0;
                }
                $finishTime = ($startTime > 0) ? $startTime + ($remMinutes * 60) : 0;
            } else { // Off, Idle
                $progress = 0;
                $elapsedMinutes = 0;
                $remMinutes = 0;
                $startTime = 0;
                $finishTime = 0;
            }

            $this->SetValue('ElapsedTime', (int)$elapsedMinutes);
            $this->SetValue('RemainingTime', (int)$remMinutes);
            $this->SetValue('RemainingTimeSeconds', (int)($remMinutes * 60));
            $this->SetValue('StartTime', (int)$startTime);
            $this->SetValue('FinishTime', (int)$finishTime);
            $this->SetValue('ProgressPct', (int)$progress);
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
