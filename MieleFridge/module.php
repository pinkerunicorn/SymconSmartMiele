<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Trait_SmartLog.php';
require_once __DIR__ . '/../libs/Trait_DeviceAvailability.php';
require_once __DIR__ . '/../libs/Trait_MieleDevice.php';
require_once __DIR__ . '/../libs/Trait_DeviceRegistration.php';

class MieleFridge extends IPSModuleStrict
{
    use DeviceRegistration_Trait;
    use SmartLog_Trait;
    use DeviceAvailability_Trait;
    use MieleDevice_Trait;


    public function Create(): void{
        parent::Create();
        
        
        $this->RegisterPropertyString('DeviceID', '');
        $this->RegisterPropertyBoolean('EnableSuperFreezing', false);

        $this->DA_RegisterAvailability(900);
        $this->DA_RegisterWatchdog();

        // Variables
        $this->RegisterVariableString('StatusText', 'Status', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'info'
        ], 15);
        
        $this->RegisterVariableInteger('Temp1', 'Ist-Temperatur (Zone 1)', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => ' °C',
            'ICON' => 'temperature-half'
        ], 20);
        $this->RegisterVariableInteger('TargetTemp1', 'Ziel-Temperatur (Zone 1)', [
            'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
            'SUFFIX' => ' °C',
            'ICON' => 'temperature-half',
            'MIN' => 2,
            'MAX' => 9,
            'STEP' => 1
        ], 25);
        $this->EnableAction('TargetTemp1');
        
        // DoorOpen wurde nach ApplyChanges verschoben, um Löschen/Neuanlegen zu erlauben

        $this->RegisterVariableBoolean('SuperCooling', 'Schnellkühlen', [
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
            'ICON' => 'power-off'
        ], 35);
        $this->EnableAction('SuperCooling');

        // SuperFreezing nur registrieren wenn in Config aktiviert
        if ($this->ReadPropertyBoolean('EnableSuperFreezing')) {
            $this->RegisterVariableBoolean('SuperFreezing', 'Schnellgefrieren', [
                'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
                'ICON' => 'snowflake'
            ], 36);
            $this->EnableAction('SuperFreezing');
        } else {
            // Variable entfernen falls vorhanden
            @$this->UnregisterVariable('SuperFreezing');
        }
    }

    public function Destroy(): void {
        parent::Destroy();
        $this->DR_Unregister();
    }

    public function ApplyChanges(): void{
        parent::ApplyChanges();
        $this->DA_ApplyPresentation();

        if (empty($this->ReadPropertyString('DeviceID'))) {
            $this->SetStatus(104);
            return;
        $this->DR_Register('DevicesGenericSensor');
        }

        IPS_SetVariableCustomProfile($this->GetIDForIdent('SuperCooling'), '');

        if ($this->ReadPropertyBoolean('EnableSuperFreezing')) {
            IPS_SetVariableCustomProfile($this->GetIDForIdent('SuperFreezing'), '');
        }
        
        // Migration: Delete legacy profiles
        if (IPS_VariableProfileExists('Miele.SuperCooling')) {
            IPS_DeleteVariableProfile('Miele.SuperCooling');
        }
        if (IPS_VariableProfileExists('Miele.SuperFreezing')) {
            IPS_DeleteVariableProfile('Miele.SuperFreezing');
        }

        $this->RegisterVariableBoolean('DoorOpen', 'Tür', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'door-closed',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => json_encode([
                ['Value' => false, 'Caption' => 'Geschlossen', 'IconValue' => 'door-closed', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0x00FF00, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0x00FF00],
                ['Value' => true, 'Caption' => 'Offen', 'IconValue' => 'door-open', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFF0000, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFF0000]
            ])
        ], 30);        // Aktionen nach CustomPresentation re-aktivieren
        $this->EnableAction('TargetTemp1');
        $this->EnableAction('SuperCooling');
        if (@$this->GetIDForIdent('SuperFreezing') !== false) {
            $this->EnableAction('SuperFreezing');
        }

    
    }

    protected function Miele_ProcessCustomDeviceData(array $state): void
    {
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
    }

    public function RequestAction(string $Ident, mixed $Value): void{
        $this->SLog('INFO', "Befehl: $Ident → " . var_export($Value, true));
        $deviceId = $this->ReadPropertyString('DeviceID');
        if (empty($deviceId)) {
            return;
        }

        $actionData = [];

        switch ($Ident) {
            case 'DA_Watchdog':
                $this->DA_HandleWatchdog();
                break;
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
            $success = $this->Miele_SendAction($actionData);

            if ($success) {
                $this->SetValue($Ident, $Value);
            }
        }
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


