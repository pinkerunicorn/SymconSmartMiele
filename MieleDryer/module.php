<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Trait_SmartLog.php';
require_once __DIR__ . '/../libs/Trait_DeviceAvailability.php';
require_once __DIR__ . '/../libs/Trait_MieleDevice.php';

class MieleDryer extends IPSModuleStrict
{
    use SmartLog_Trait;
    use DeviceAvailability_Trait;
    use MieleDevice_Trait;


    public function Create(): void{
        parent::Create();
        
        
        $this->RegisterPropertyString('DeviceID', '');

        $this->DA_RegisterAvailability(900);
        $this->DA_RegisterWatchdog();

        $this->RegisterAttributeFloat('LastEnergy', 0.0);
        $this->RegisterAttributeInteger('AnchorStartTime', 0);

        // Variables
        $this->RegisterVariableInteger('PowerSupply', 'Spannungsversorgung', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'power-off',
            'INTERVALS_ACTIVE' => true,
            'INTERVALS' => json_encode([
                [ 'IntervalMinValue' => 0, 'IntervalMaxValue' => 1, 'ConstantActive' => true, 'ConstantValue' => 'Unbekannt', 'ConversionFactor' => 1, 'PrefixActive' => false, 'PrefixValue' => '', 'SuffixActive' => false, 'SuffixValue' => '', 'DigitsActive' => false, 'DigitsValue' => 0, 'IconActive' => true, 'IconValue' => 'power-off', 'ColorActive' => true, 'ColorValue' => -1, 'ContentColorActive' => true, 'ContentColorValue' => 0xFFFFFF ],
                [ 'IntervalMinValue' => 1, 'IntervalMaxValue' => 2, 'ConstantActive' => true, 'ConstantValue' => 'Eingeschaltet', 'ConversionFactor' => 1, 'PrefixActive' => false, 'PrefixValue' => '', 'SuffixActive' => false, 'SuffixValue' => '', 'DigitsActive' => false, 'DigitsValue' => 0, 'IconActive' => true, 'IconValue' => 'power-off', 'ColorActive' => true, 'ColorValue' => 0x00CC00, 'ContentColorActive' => true, 'ContentColorValue' => 0xFFFFFF ],
                [ 'IntervalMinValue' => 2, 'IntervalMaxValue' => 3, 'ConstantActive' => true, 'ConstantValue' => 'Ausgeschaltet', 'ConversionFactor' => 1, 'PrefixActive' => false, 'PrefixValue' => '', 'SuffixActive' => false, 'SuffixValue' => '', 'DigitsActive' => false, 'DigitsValue' => 0, 'IconActive' => true, 'IconValue' => 'power-off', 'ColorActive' => true, 'ColorValue' => 0xFF0000, 'ContentColorActive' => true, 'ContentColorValue' => 0xFFFFFF ]
            ])
        ], 5);

        $this->RegisterVariableBoolean('PowerOn', 'Eingeschaltet', [
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
            'ICON' => 'power-off'
        ], 8);
        $this->EnableAction('PowerOn');

        // Aktion als Dropdown
        $processActionPres = [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'ICON' => 'play',
            'OPTIONS' => json_encode([
                ['Value' => 0, 'Caption' => 'Keine Aktion', 'IconActive' => false, 'IconValue' => '', 'Color' => -1],
                ['Value' => 1, 'Caption' => 'Start', 'IconActive' => true, 'IconValue' => 'play', 'Color' => 0x00CC00],
                ['Value' => 2, 'Caption' => 'Stop', 'IconActive' => true, 'IconValue' => 'Close', 'Color' => 0xFF0000],
                ['Value' => 3, 'Caption' => 'Pause', 'IconActive' => true, 'IconValue' => 'clock', 'Color' => 0xFFAA00]
            ])
        ];
        $this->RegisterVariableInteger('ProcessAction', 'Aktion', $processActionPres, 9);
        $this->EnableAction('ProcessAction');

        $this->RegisterVariableString('StatusText', 'Status', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'info'
        ], 10);
        $this->RegisterVariableBoolean('SignalInfo', 'Hinweis vorhanden', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'circle-info',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => json_encode([
                ['Value' => false, 'Caption' => 'Kein Hinweis', 'IconValue' => 'circle-info', 'IconActive' => false, 'ColorActive' => false, 'ColorDisplay' => -1, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => -1],
                ['Value' => true, 'Caption' => 'Hinweis!', 'IconValue' => 'triangle-exclamation', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFFA500, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFFA500]
            ])
        ], 11);
        $this->RegisterVariableBoolean('SignalFailure', 'Fehler erkannt', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'triangle-exclamation',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => json_encode([
                ['Value' => false, 'Caption' => 'OK', 'IconValue' => 'circle-check', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0x00CC00, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0x00CC00],
                ['Value' => true, 'Caption' => 'Fehler!', 'IconValue' => 'triangle-exclamation', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFF0000, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFF0000]
            ])
        ], 12);
        
        $this->RegisterVariableString('ProgramName', 'Programmbezeichnung', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'bars-progress'
        ], 21);
        $this->RegisterVariableString('ProgramPhaseText', 'Programm-Phase', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'bars-progress'
        ], 22);
        
        $this->RegisterVariableInteger('ElapsedTime', 'Verstrichene Zeit', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => 'min',
            'ICON' => 'stopwatch'
        ], 27);
        
        $this->RegisterVariableString('StartTime', 'Start um', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'calendar-days'
        ], 23);
        $this->RegisterVariableString('FinishTime', 'Ende um', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'calendar-days'
        ], 24);
        $this->RegisterVariableInteger('RemainingTime', 'Verbleibende Zeit', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => 'min',
            'ICON' => 'hourglass-half'
        ], 28);
        $this->RegisterVariableInteger('RemainingTimeSeconds', 'Verbleibende Zeit (Sekunden)', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => 's',
            'ICON' => 'hourglass-half'
        ], 29);
        $this->RegisterVariableInteger('ProgressPct', 'Arbeitsfortschritt', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => '%',
            'ICON' => 'bars-progress'
        ], 30);
        
        $this->RegisterVariableBoolean('Door', 'Tür', [
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
        ], 33);

        $this->RegisterVariableString('DrynessLevel', 'Trocknungsstufe', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'droplet'
        ], 35);
        
        $this->RegisterVariableFloat('CurrentEnergyConsumption', 'aktueller Energieverbrauch', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => 'kWh',
            'ICON' => 'bolt'
        ], 55);
        
        $this->RegisterVariableFloat('LastEnergyConsumption', 'letzter Energieverbrauch', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => 'kWh',
            'ICON' => 'bolt'
        ], 60);
    }

    public function ApplyChanges(): void{
        parent::ApplyChanges();
        $this->DA_ApplyPresentation();

        $this->RegisterVariableBoolean('Door', 'Tür', [
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
        ], 33);

        if (empty($this->ReadPropertyString('DeviceID'))) {
            $this->SetStatus(104);
            return;
        }



        IPS_SetVariableCustomProfile($this->GetIDForIdent('ProcessAction'), '');
        $this->EnableAction('ProcessAction');
        $this->EnableAction('PowerOn');
        
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

        IPS_SetVariableCustomProfile($this->GetIDForIdent('PowerSupply'), '');
        IPS_SetVariableCustomProfile($this->GetIDForIdent('PowerOn'), '');

    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'DA_Watchdog':
                $this->DA_HandleWatchdog();
                break;
            case 'PowerOn':
                $action = $Value ? 'powerOn' : 'powerOff';
                $this->Miele_SendAction([$action => true]);
                $this->SetValue($Ident, $Value);
                break;
            case 'ProcessAction':
                if ($Value == 1) {
                    $this->Miele_SendAction(['processAction' => 1]);
                } elseif ($Value == 2) {
                    $this->Miele_SendAction(['processAction' => 2]);
                } elseif ($Value == 3) {
                    $this->Miele_SendAction(['processAction' => 3]);
                }
                $this->SetValue($Ident, 0);
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
