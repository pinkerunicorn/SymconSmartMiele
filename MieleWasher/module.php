<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Trait_SmartLog.php';
require_once __DIR__ . '/../libs/Trait_DeviceAvailability.php';
require_once __DIR__ . '/../libs/Trait_MieleDevice.php';

class MieleWasher extends IPSModuleStrict
{
    use SmartLog_Trait;
    use DeviceAvailability_Trait;
    use MieleDevice_Trait;


    public function Create(): void{
        parent::Create();
        
        
        $this->RegisterPropertyString('DeviceID', '');
        $this->RegisterPropertyBoolean('EnableTwinDos', true);

        $this->DA_RegisterAvailability(900);
        $this->DA_RegisterWatchdog();

        $this->RegisterAttributeInteger('LastTwinDos1', 0);
        $this->RegisterAttributeInteger('LastTwinDos2', 0);
        $this->RegisterAttributeFloat('LastEnergy', 0.0);
        $this->RegisterAttributeFloat('LastWater', 0.0);
        $this->RegisterAttributeInteger('AnchorStartTime', 0);

        // Connect to Splitter

        
        // Variables
        $this->RegisterVariableInteger('PowerSupply', 'Spannungsversorgung', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Power',
            'INTERVALS_ACTIVE' => true,
            'INTERVALS' => json_encode([
                [ 'IntervalMinValue' => 0, 'IntervalMaxValue' => 1, 'ConstantActive' => true, 'ConstantValue' => 'Unbekannt', 'ConversionFactor' => 1, 'PrefixActive' => false, 'PrefixValue' => '', 'SuffixActive' => false, 'SuffixValue' => '', 'DigitsActive' => false, 'DigitsValue' => 0, 'IconActive' => true, 'IconValue' => 'Power', 'ColorActive' => true, 'ColorValue' => -1, 'ContentColorActive' => true, 'ContentColorValue' => 0xFFFFFF ],
                [ 'IntervalMinValue' => 1, 'IntervalMaxValue' => 2, 'ConstantActive' => true, 'ConstantValue' => 'Eingeschaltet', 'ConversionFactor' => 1, 'PrefixActive' => false, 'PrefixValue' => '', 'SuffixActive' => false, 'SuffixValue' => '', 'DigitsActive' => false, 'DigitsValue' => 0, 'IconActive' => true, 'IconValue' => 'Power', 'ColorActive' => true, 'ColorValue' => 0x00CC00, 'ContentColorActive' => true, 'ContentColorValue' => 0xFFFFFF ],
                [ 'IntervalMinValue' => 2, 'IntervalMaxValue' => 3, 'ConstantActive' => true, 'ConstantValue' => 'Ausgeschaltet', 'ConversionFactor' => 1, 'PrefixActive' => false, 'PrefixValue' => '', 'SuffixActive' => false, 'SuffixValue' => '', 'DigitsActive' => false, 'DigitsValue' => 0, 'IconActive' => true, 'IconValue' => 'Power', 'ColorActive' => true, 'ColorValue' => 0xFF0000, 'ContentColorActive' => true, 'ContentColorValue' => 0xFFFFFF ]
            ])
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
            'ICON' => 'Information',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => json_encode([
                ['Value' => false, 'Caption' => 'Kein Hinweis', 'IconValue' => 'Information', 'IconActive' => false, 'ColorActive' => false, 'ColorDisplay' => -1, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => -1],
                ['Value' => true, 'Caption' => 'Hinweis!', 'IconValue' => 'Warning', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFFA500, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFFA500]
            ])
        ], 11);
        $this->RegisterVariableBoolean('SignalFailure', 'Fehler erkannt', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Alert',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => json_encode([
                ['Value' => false, 'Caption' => 'OK', 'IconValue' => 'Ok', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0x00CC00, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0x00CC00],
                ['Value' => true, 'Caption' => 'Fehler!', 'IconValue' => 'Alert', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFF0000, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFF0000]
            ])
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
            'ICON' => 'door-closed',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => json_encode([
                ['Value' => false, 'Caption' => 'Offen', 'IconValue' => 'door-open', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFF0000, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFF0000],
                ['Value' => true, 'Caption' => 'Geschlossen', 'IconValue' => 'door-closed', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0x00FF00, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0x00FF00]
            ])
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
        $this->DA_ApplyPresentation();

        if (empty($this->ReadPropertyString('DeviceID'))) {
            $this->SetStatus(104);
            return;
        }

        IPS_SetVariableCustomProfile($this->GetIDForIdent('ProcessAction'), '');
        $this->EnableAction('ProcessAction');
        $this->EnableAction('PowerOn');

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



    }

    public function Miele_OnDeviceUpdate(string $deviceId): void
    {
        if ($this->ReadPropertyBoolean('EnableTwinDos')) {
            $this->FetchFillingLevels($deviceId);
        }
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


    

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'DA_Watchdog':
                $this->DA_HandleWatchdog();
                break;
            case 'PowerOn':
                if ($Value) {
                    $this->Miele_SendAction(['powerOn' => true]);
                } else {
                    $this->Miele_SendAction(['powerOff' => true]);
                }
                $this->SetValue('PowerOn', (bool)$Value);
                break;
            case 'ProcessAction':
                if ($Value > 0) {
                    $this->Miele_SendAction(['processAction' => (int)$Value]);
                }
                $this->SetValue('ProcessAction', (int)$Value);
                break;
        }
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
