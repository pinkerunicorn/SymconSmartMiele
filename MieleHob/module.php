<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Trait_SmartLog.php';

class MieleHob extends IPSModuleStrict
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
        $this->RegisterPropertyInteger('PlateCount', 4);

        // Variables
        $this->RegisterVariableBoolean('IsActive', 'Kochfeld aktiv', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Flame'
        ], 5);
        $this->RegisterVariableInteger('ActiveZoneCount', 'Aktive Kochzonen', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Flame'
        ], 6);
        $this->RegisterVariableString('StatusText', 'Status', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Information'
        ], 10);
        
        if (!IPS_VariableProfileExists('SM.Miele.PowerSupply')) {
            IPS_CreateVariableProfile('SM.Miele.PowerSupply', 1);
            IPS_SetVariableProfileAssociation('SM.Miele.PowerSupply', 0, 'Unbekannt', '', -1);
            IPS_SetVariableProfileAssociation('SM.Miele.PowerSupply', 1, 'Eingeschaltet', '', -1);
            IPS_SetVariableProfileAssociation('SM.Miele.PowerSupply', 2, 'Ausgeschaltet', '', -1);
        }
        $this->RegisterVariableInteger('PowerSupply', 'Spannungsversorgung', 'SM.Miele.PowerSupply', 5);

        if (!IPS_VariableProfileExists('SM.Miele.PlateStep')) {
            IPS_CreateVariableProfile('SM.Miele.PlateStep', 1);
            IPS_SetVariableProfileAssociation('SM.Miele.PlateStep', 0, 'Aus', '', -1);
            for ($j = 1; $j <= 9; $j++) {
                IPS_SetVariableProfileAssociation('SM.Miele.PlateStep', $j, (string)$j, '', -1);
            }
            IPS_SetVariableProfileAssociation('SM.Miele.PlateStep', 10, 'Booster', '', -1);
            IPS_SetVariableProfileAssociation('SM.Miele.PlateStep', 11, 'TwinBooster', '', -1);
            IPS_SetVariableProfileAssociation('SM.Miele.PlateStep', 12, 'TwinBooster+', '', -1);
        }
        
        for ($i = 1; $i <= 5; $i++) {
            $this->RegisterVariableInteger('PlateStep' . $i, 'Leistungsstufe ' . $i, 'SM.Miele.PlateStep', 19 + $i);
        }
        
        // Dynamisch je nach Modell Kochzonen anlegen (meistens 4-6)
        // Wir legen prophylaktisch 4 an
        for ($i=1; $i<=4; $i++) {
            $this->RegisterVariableString('Plate'. $i, 'Kochzone '. $i, [
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                'ICON' => 'Flame'
            ], 20 + $i);
        }
    }

    public function ApplyChanges(): void{
        parent::ApplyChanges();


        $plates = $this->ReadPropertyInteger('PlateCount');
        
        for ($i = 1; $i <= $plates; $i++) {
            $ident = 'Plate'. $i;
            $id = @$this->GetIDForIdent($ident);
            if ($id !== false && IPS_VariableExists($id)) {
                $var = IPS_GetVariable($id);
                if ($var['VariableType'] !== 3 /* String */) {
                    $this->UnregisterVariable($ident);
                }
            }
            
            $this->RegisterVariableString($ident, 'Kochzone '. $i, [
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                'ICON' => 'Flame'
            ], 20 + $i);
        }
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
            } elseif ($type === 'ActionsUpdate') {
                // Das Kochfeld ist read-only, daher ignorieren wir ActionsUpdates
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

            if (isset($state['powerSupply']['value_raw'])) {
                $this->SetValue('PowerSupply', (int)$state['powerSupply']['value_raw']);
            }

            if (isset($state['plateStep']) && is_array($state['plateStep'])) {
                $plates = $this->ReadPropertyInteger('PlateCount');
                for ($i = 0; $i < $plates; $i++) {
                    if (isset($state['plateStep'][$i]['value_localized'])) {
                        $this->SetValue('Plate'. ($i + 1), (string)$state['plateStep'][$i]['value_localized']);
                    }
                }

                foreach ($state['plateStep'] as $i => $step) {
                    $zoneNum = $i + 1;
                    if ($zoneNum <= 5 && isset($step['value_raw'])) {
                        $this->SetValue('PlateStep' . $zoneNum, (int)$step['value_raw']);
                    }
                }

                $activeCount = 0;
                foreach ($state['plateStep'] as $step) {
                    if (isset($step['value_raw']) && (int)$step['value_raw'] > 0) $activeCount++;
                }
                $this->SetValue('IsActive', $activeCount > 0);
                $this->SetValue('ActiveZoneCount', $activeCount);
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

    protected function LogMessage(string $Message, int $Type): bool
    {
        $this->SLog('INFO', $Message);
        IPS_LogMessage('SmartVillaKunterbunt', 'MieleHob: '. $Message);
        return true;
    }

    public function GetConfigurationForm(): string
    {
        return <<<'EOT'
{
    "elements": [
        {
            "type": "Label",
            "caption": "Damit ich dein Kochfeld finde, trag bitte hier die Miele Device ID (fabNumber) ein."
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
                    "type": "NumberSpinner",
                    "name": "PlateCount",
                    "caption": "Anzahl Kochzonen",
                    "minimum": 1,
                    "maximum": 6
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
