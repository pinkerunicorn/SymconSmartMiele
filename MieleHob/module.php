<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Trait_SmartLog.php';
require_once __DIR__ . '/../libs/Trait_DeviceAvailability.php';
require_once __DIR__ . '/../libs/Trait_MieleDevice.php';
class MieleHob extends IPSModuleStrict
{
    use SmartLog_Trait;
    use DeviceAvailability_Trait;
    use MieleDevice_Trait;


    public function Create(): void{
        parent::Create();
        
        $this->RegisterPropertyString('DeviceID', '');
        $this->RegisterPropertyInteger('PlateCount', 4);

        $this->DA_RegisterAvailability(900);
        $this->DA_RegisterWatchdog();

        // Variables
        $this->RegisterVariableBoolean('IsActive', 'Kochfeld aktiv', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'fire-burner',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => json_encode([
                ['Value' => false, 'Caption' => 'Inaktiv', 'IconValue' => 'fire', 'IconActive' => true, 'ColorActive' => false, 'ColorDisplay' => -1, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => -1],
                ['Value' => true, 'Caption' => 'Aktiv', 'IconValue' => 'fire', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFF6600, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFF6600]
            ])
        ], 5);
        $this->RegisterVariableInteger('ActiveZoneCount', 'Aktive Kochzonen', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'fire-burner'
        ], 6);
        $this->RegisterVariableString('StatusText', 'Status', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'info'
        ], 10);
        
        $this->RegisterVariableInteger('PowerSupply', 'Spannungsversorgung', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'power-off',
            'INTERVALS_ACTIVE' => true,
            'INTERVALS' => json_encode([
                [ 'IntervalMinValue' => 0, 'IntervalMaxValue' => 1, 'ConstantActive' => true, 'ConstantValue' => 'Unbekannt', 'ConversionFactor' => 1, 'PrefixActive' => false, 'PrefixValue' => '', 'SuffixActive' => false, 'SuffixValue' => '', 'DigitsActive' => false, 'DigitsValue' => 0, 'IconActive' => true, 'IconValue' => 'power-off', 'ColorActive' => true, 'ColorValue' => -1, 'ContentColorActive' => true, 'ContentColorValue' => 0xFFFFFF ],
                [ 'IntervalMinValue' => 1, 'IntervalMaxValue' => 2, 'ConstantActive' => true, 'ConstantValue' => 'Eingeschaltet', 'ConversionFactor' => 1, 'PrefixActive' => false, 'PrefixValue' => '', 'SuffixActive' => false, 'SuffixValue' => '', 'DigitsActive' => false, 'DigitsValue' => 0, 'IconActive' => true, 'IconValue' => 'power-off', 'ColorActive' => true, 'ColorValue' => 0x00CC00, 'ContentColorActive' => true, 'ContentColorValue' => 0xFFFFFF ],
                [ 'IntervalMinValue' => 2, 'IntervalMaxValue' => 3, 'ConstantActive' => true, 'ConstantValue' => 'Ausgeschaltet', 'ConversionFactor' => 1, 'PrefixActive' => false, 'PrefixValue' => '', 'SuffixActive' => false, 'SuffixValue' => '', 'DigitsActive' => false, 'DigitsValue' => 0, 'IconActive' => true, 'IconValue' => 'power-off', 'ColorActive' => true, 'ColorValue' => 0xFF0000, 'ContentColorActive' => true, 'ContentColorValue' => 0xFFFFFF ]
            ])
        ], 7);

        for ($i = 1; $i <= 5; $i++) {
            $this->RegisterVariableString('PlateStep' . $i, 'Leistungsstufe ' . $i, [
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                'ICON' => 'sliders'
            ], 19 + $i);
        }
        
        // Dynamisch je nach Modell Kochzonen anlegen (meistens 4-6)
        // Wir legen prophylaktisch 4 an
        for ($i=1; $i<=4; $i++) {
            $this->RegisterVariableString('Plate'. $i, 'Kochzone '. $i, [
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                'ICON' => 'fire'
            ], 24 + $i);
        }
    }

    public function Destroy(): void {
        parent::Destroy();
        }

    public function ApplyChanges(): void{
        parent::ApplyChanges();
        $this->DA_ApplyPresentation();

        if (empty($this->ReadPropertyString('DeviceID'))) {
            $this->SetStatus(104);
            return;
        }

        IPS_SetVariableCustomProfile($this->GetIDForIdent('PowerSupply'), '');
        
        if (IPS_VariableProfileExists('Miele.PowerSupply')) {
            IPS_DeleteVariableProfile('Miele.PowerSupply');
        }



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
                'ICON' => 'fire'
            ], 24 + $i);
        }

    
    }

    protected function Miele_ProcessCustomDeviceData(array $state): void
    {
        if (isset($state['plateStep']) && is_array($state['plateStep'])) {
            $plates = $this->ReadPropertyInteger('PlateCount');
            for ($i = 0; $i < $plates; $i++) {
                if (isset($state['plateStep'][$i]['value_localized'])) {
                    $this->SetValueIfChanged('Plate'. ($i + 1), (string)$state['plateStep'][$i]['value_localized']);
                }
            }

            $stepMap = [0 => 'Aus', 10 => 'Booster', 11 => 'TwinBooster', 12 => 'TwinBooster+'];
            foreach ($state['plateStep'] as $i => $step) {
                $zoneNum = $i + 1;
                if ($zoneNum <= 5 && isset($step['value_raw'])) {
                    $raw = (int)$step['value_raw'];
                    if ($raw >= 1 && $raw <= 9) {
                        $mapped = (string)$raw;
                    } else {
                        $mapped = $stepMap[$raw] ?? (string)$raw;
                    }
                    $this->SetValueIfChanged('PlateStep' . $zoneNum, $mapped);
                }
            }

            $activeCount = 0;
            foreach ($state['plateStep'] as $step) {
                if (isset($step['value_raw']) && (int)$step['value_raw'] > 0) $activeCount++;
            }
            $this->SetValueIfChanged('IsActive', $activeCount > 0);
            $this->SetValueIfChanged('ActiveZoneCount', $activeCount);
        }
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'DA_Watchdog':
                $this->DA_HandleWatchdog();
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
