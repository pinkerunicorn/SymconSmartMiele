<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Trait_SmartLog.php';

class MieleHood extends IPSModuleStrict
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

        // Connect to Splitter

        
        // Variables
        $this->RegisterVariableString('StatusText', 'Status', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Information'
        ], 10);
        
        $this->RegisterVariableBoolean('Light', 'Licht', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Bulb'
        ], 20);
        $this->EnableAction('Light');
        
        $this->RegisterVariableInteger('VentilationStep', 'Lüfterstufe', [
            'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
            'MIN' => 0.0,
            'MAX' => 4.0,
            'STEP' => 1.0,
            'SUFFIX' => 'Stufe',
            'ICON' => 'Ventilator'
        ], 30);
        $this->EnableAction('VentilationStep');
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

            if (isset($data['Devices'][$deviceId])) {
                $deviceData = $data['Devices'][$deviceId];
                $this->ProcessDeviceData($deviceData);
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

            // Light (Miele API: 1=On, 2=Off)
            if (isset($state['light'])) {
                $isLightOn = ($state['light'] == 1);
                $this->SetValue('Light', (bool)$isLightOn);
            }

            // VentilationStep
            if (isset($state['ventilationStep']['value_raw'])) {
                $this->SetValue('VentilationStep', (int)$state['ventilationStep']['value_raw']);
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

    protected function Log(string $text): void
    {
        $this->SLog('INFO', $text);
    }

    public function RequestAction(string $Ident, mixed $Value): void{
        $deviceId = $this->ReadPropertyString('DeviceID');
        if (empty($deviceId)) {
            $this->Log("Device ID not configured.");
            echo "Device ID not configured.\n";
            return;
        }

        $actionData = [];

        switch ($Ident) {
            case 'Light':
                // Miele API: 1=On, 2=Off
                $actionData['light'] = $Value ? 1 : 2;
                $this->Log("Schalte Licht: ". ($Value ? 'An': 'Aus'));
                break;
            
            case 'VentilationStep':
                $actionData['ventilationStep'] = $Value;
                $this->Log("Setze Lüfterstufe: ". $Value);
                break;

            default:
                throw new Exception('Invalid Action');
        }

        if (!empty($actionData)) {
            // Forward to Splitter
            $payload = [
                'DataID'=> '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}',
                'Command'=> 'ExecuteAction',
                'DeviceID'=> $deviceId,
                'ActionData'=> $actionData
            ];
            
            $result = $this->SendDataToParent(json_encode($payload));
            $success = json_decode($result, true);

            if ($success) {
                $this->SetValue($Ident, $Value);
            } else {
                $this->Log("Fehler beim Ausführen der Aktion.");
                echo "Fehler beim Ausführen der Aktion.\n";
            }
        }
    }

    protected function LogMessage(string $Message, int $Type): bool
    {
        $this->SLog('INFO', $Message);
        IPS_LogMessage('SmartVillaKunterbunt', 'MieleHood: '. $Message);
        return true;
    }

    public function GetConfigurationForm(): string
    {
        return <<<'EOT'
{
    "elements": [
        {
            "type": "Label",
            "caption": "Damit ich deine Dunstabzugshaube finde, trag bitte hier die Miele Device ID (fabNumber) ein."
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


