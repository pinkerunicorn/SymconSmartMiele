<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Trait_SmartLog.php';

class MieleFridge extends IPSModuleStrict
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
        $this->RegisterPropertyBoolean('EnableSuperFreezing', false);

        // Variables
        $this->RegisterVariableString('StatusText', 'Status', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Information'
        ], 15);
        
        $this->RegisterVariableInteger('Temp1', 'Ist-Temperatur (Zone 1)', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => ' °C',
            'ICON' => 'Temperature'
        ], 20);
        $this->RegisterVariableInteger('TargetTemp1', 'Ziel-Temperatur (Zone 1)', [
            'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
            'SUFFIX' => ' °C',
            'ICON' => 'Temperature',
            'MIN' => 2,
            'MAX' => 9,
            'STEP' => 1
        ], 25);
        $this->EnableAction('TargetTemp1');
        
        // Tür-Profil
        if (!IPS_VariableProfileExists('SM.Miele.Door')) {
            IPS_CreateVariableProfile('SM.Miele.Door', 0);
            IPS_SetVariableProfileAssociation('SM.Miele.Door', 0, 'Geschlossen', 'Window', 0x00CC00);
            IPS_SetVariableProfileAssociation('SM.Miele.Door', 1, 'Geöffnet', 'Window', 0xFF6600);
        }
        $this->RegisterVariableBoolean('DoorOpen', 'Tür', 'SM.Miele.Door', 30);

        $this->RegisterVariableBoolean('SuperCooling', 'Schnellkühlen', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Power'
        ], 35);
        $this->EnableAction('SuperCooling');

        // SuperFreezing nur registrieren wenn in Config aktiviert
        if ($this->ReadPropertyBoolean('EnableSuperFreezing')) {
            $this->RegisterVariableBoolean('SuperFreezing', 'Schnellgefrieren', [
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                'ICON' => 'Snowflake'
            ], 36);
            $this->EnableAction('SuperFreezing');
        } else {
            // Variable entfernen falls vorhanden
            @$this->UnregisterVariable('SuperFreezing');
        }
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

            $type = $data['Type'] ?? 'DeviceUpdate';

            if ($type === 'DeviceUpdate') {
                if (isset($data['Devices'][$deviceId])) {
                    $this->ProcessDeviceData($data['Devices'][$deviceId]);
                }
            } elseif ($type === 'ActionsUpdate') {
                if (isset($data['Actions'][$deviceId])) {
                    $this->SetBuffer('ActionsData', json_encode($data['Actions'][$deviceId]));
                }
            }
        }
    
        return "";
    }

    private function ProcessDeviceData(array $deviceData): void
    {
        if (isset($deviceData['state'])) {
            $state = $deviceData['state'];

            if (isset($state['status']['value_localized'])) {
                $this->SetValue('StatusText', (string)$state['status']['value_localized']);
            }
            if (isset($state['status']['value_raw'])) {
                $statusRaw = $state['status']['value_raw'];
                $isSuperCooling = ($statusRaw == 14 || $statusRaw == 146);
                $isSuperFreezing = ($statusRaw == 15 || $statusRaw == 147);

                $actionsData = $this->GetBuffer('ActionsData');
                if ($actionsData) {
                    $actions = json_decode($actionsData, true);
                    if (isset($actions['processAction']) && is_array($actions['processAction'])) {
                        if (in_array(7, $actions['processAction'])) {
                            $isSuperCooling = true;
                        }
                        if (in_array(5, $actions['processAction'])) {
                            $isSuperFreezing = true;
                        }
                    }
                }

                $this->SetValue('SuperCooling', $isSuperCooling);
                if ($this->ReadPropertyBoolean('EnableSuperFreezing')) {
                    $this->SetValue('SuperFreezing', $isSuperFreezing);
                }
            }

            if (isset($state['temperature'][0]['value_raw'])) {
                $valTemp = (int)round($state['temperature'][0]['value_raw'] / 100.0);
                $this->SendDebug('Temp Update', 'Raw: '. $valTemp . 'Type: '. gettype($valTemp), 0);
                $this->SetValue('Temp1', $valTemp);
            }
            if (isset($state['targetTemperature'][0]['value_raw'])) {
                $valTarget = (int)round($state['targetTemperature'][0]['value_raw'] / 100.0);
                $this->SetValue('TargetTemp1', $valTarget);
            }
            
            if (isset($state['signalDoor'])) {
                $this->SetValue('DoorOpen', (bool)$state['signalDoor']);
            }
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

    public function RequestAction(string $Ident, mixed $Value): void{
        $this->SLog('INFO', "Befehl: $Ident → " . var_export($Value, true));
        $deviceId = $this->ReadPropertyString('DeviceID');
        if (empty($deviceId)) {
            return;
        }

        $actionData = [];

        switch ($Ident) {
            case 'TargetTemp1':
                $actionData['targetTemperature'] = [
                    [
                        'zone'=> 1,
                        'value'=> (int)round($Value * 100)
                    ]
                ];
                break;
            case 'SuperCooling':
                $actionData['processAction'] = $Value ? 6 : 7;
                break;
            case 'SuperFreezing':
                $actionData['processAction'] = $Value ? 4 : 5;
                break;
            default:
                throw new Exception('Invalid Action');
        }

        if (!empty($actionData)) {
            $success = $this->SendAction($deviceId, $actionData);

            if ($success) {
                $this->SetValue($Ident, $Value);
            }
        }
    }

    private function SendAction(string $deviceId, array $actionData): bool
    {
        $payload = [
            'DataID'=> '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}',
            'Command'=> 'ExecuteAction',
            'DeviceID'=> $deviceId,
            'ActionData'=> $actionData
        ];
        
        $result = $this->SendDataToParent(json_encode($payload));
        return (bool)json_decode($result, true);
    }

    protected function LogMessage(string $Message, int $Type): bool
    {
        $this->SLog('INFO', $Message);
        IPS_LogMessage('SmartVillaKunterbunt', 'MieleFridge: '. $Message);
        return true;
    }

    public function GetConfigurationForm(): string
    {
        return <<<'EOT'
{
    "elements": [
        {
            "type": "Label",
            "caption": "Damit ich deinen Kühlschrank finde, trag bitte hier die Miele Device ID (fabNumber) ein."
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
        },
        {
            "type": "CheckBox",
            "name": "EnableSuperFreezing",
            "caption": "Gefrierfach vorhanden (Schnellgefrieren aktivieren)"
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


