<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Trait_SmartLog.php';
require_once __DIR__ . '/../libs/Trait_CentralStateAware.php';
require_once __DIR__ . '/../libs/Trait_DeviceAvailability.php';
require_once __DIR__ . '/../libs/Trait_MieleDevice.php';
class MieleHood extends IPSModuleStrict
{
    use SmartLog_Trait;
    use CentralStateAware_Trait;
    use DeviceAvailability_Trait;
    use MieleDevice_Trait;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('DeviceID', '');

        $this->DA_RegisterAvailability(900);
        $this->DA_RegisterWatchdog();

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

        $this->RegisterVariableString('StatusText', 'Status', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'info'
        ], 10);

        $this->RegisterVariableBoolean('PowerOn', 'Eingeschaltet', [
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
            'ICON' => 'power-off'
        ], 15);
        $this->EnableAction('PowerOn');

        $this->RegisterVariableBoolean('Light', 'Licht', [
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
            'ICON' => 'lightbulb'
        ], 20);
        $this->EnableAction('Light');

        $this->RegisterVariableBoolean('AmbientLight', 'Stimmungslicht', [
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
            'ICON' => 'lightbulb'
        ], 25);
        $this->EnableAction('AmbientLight');

        // Lüfterstufe als Dropdown
        $ventilationPres = [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'ICON' => 'fan',
            'OPTIONS' => json_encode([
                ['Value' => 0, 'Caption' => 'Aus', 'IconActive' => false, 'IconValue' => '', 'Color' => -1],
                ['Value' => 1, 'Caption' => 'Stufe 1', 'IconActive' => true, 'IconValue' => 'fan', 'Color' => 0x00CC00],
                ['Value' => 2, 'Caption' => 'Stufe 2', 'IconActive' => true, 'IconValue' => 'fan', 'Color' => 0xFFAA00],
                ['Value' => 3, 'Caption' => 'Stufe 3', 'IconActive' => true, 'IconValue' => 'fan', 'Color' => 0xFF6600],
                ['Value' => 4, 'Caption' => 'Booster', 'IconActive' => true, 'IconValue' => 'fan', 'Color' => 0xFF0000]
            ])
        ];
        $this->RegisterVariableInteger('VentilationStep', 'Lüfterstufe', $ventilationPres, 30);
        $this->EnableAction('VentilationStep');

        $this->RegisterVariableBoolean('SignalInfo', 'Hinweis vorhanden (z.B. Filter)', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'triangle-exclamation',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => json_encode([
                ['Value' => false, 'Caption' => 'Kein Hinweis', 'IconValue' => 'circle-info', 'IconActive' => true, 'ColorActive' => false, 'ColorDisplay' => -1, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => -1],
                ['Value' => true, 'Caption' => 'Hinweis!', 'IconValue' => 'triangle-exclamation', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFFA500, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFFA500]
            ])
        ], 40);
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
        ], 41);

        $this->RegisterVariableInteger('GreaseFilterSaturation', 'Fettfilter-Sättigung', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON'         => 'filter',
            'SUFFIX'       => '%'
        ], 50);

        $this->RegisterVariableInteger('CarbonFilterSaturation', 'Kohlefilter-Sättigung', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON'         => 'filter',
            'SUFFIX'       => '%'
        ], 51);
    }

    public function Destroy(): void {
        parent::Destroy();
        }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->DA_ApplyPresentation();

        if (empty($this->ReadPropertyString('DeviceID'))) {
            $this->SetStatus(104);
            return;
        }
        
        $this->SubscribeToCentralStates(['FireplaceActive']);

        IPS_SetVariableCustomProfile($this->GetIDForIdent('VentilationStep'), '');
        
        // Migration: Delete legacy profiles
        if (IPS_VariableProfileExists('Miele.PowerSupply')) {
            IPS_DeleteVariableProfile('Miele.PowerSupply');
        }
        if (IPS_VariableProfileExists('Miele.PowerOn')) {
            IPS_DeleteVariableProfile('Miele.PowerOn');
        }
        if (IPS_VariableProfileExists('Miele.Light')) {
            IPS_DeleteVariableProfile('Miele.Light');
        }
        if (IPS_VariableProfileExists('Miele.AmbientLight')) {
            IPS_DeleteVariableProfile('Miele.AmbientLight');
        }
        if (IPS_VariableProfileExists('Miele.VentilationStep')) {
            IPS_DeleteVariableProfile('Miele.VentilationStep');
        }

        // Aktionen nach CustomPresentation re-aktivieren
        $this->EnableAction('PowerOn');
        $this->EnableAction('Light');
        $this->EnableAction('AmbientLight');
        $this->EnableAction('VentilationStep');

    
    }

    //==========================================================================
    // Data Reception (from Splitter via SSE)
    //==========================================================================

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($this->HandleCentralStateMessage($SenderID, $Message, $Data)) return;
    }

    protected function OnCentralStateChanged(string $stateName, mixed $newValue): void
    {
        if ($stateName === 'FireplaceActive' && $newValue) {
            $this->SLogInfo('Kamin ist aktiv! Schalte Dunstabzugshaube zur Sicherheit aus.');
            if ($this->GetValue('PowerOn')) {
                $this->RequestAction('PowerOn', false);
            }
        }
    }

    protected function Miele_ProcessCustomDeviceData(array $state): void
    {
        if (isset($state['light'])) {
            $this->SetValueIfChanged('Light', (bool)($state['light'] == 1));
        }
        if (isset($state['ambientLight'])) {
            $this->SetValueIfChanged('AmbientLight', (bool)($state['ambientLight'] == 1));
        }
        if (isset($state['ventilationStep']['value_raw'])) {
            $this->SetValueIfChanged('VentilationStep', (int)$state['ventilationStep']['value_raw']);
        }
        if (isset($state['greaseFilterSaturation'])) {
            $this->SetValueIfChanged('GreaseFilterSaturation', (int)$state['greaseFilterSaturation']);
        }
        if (isset($state['carbonFilterSaturation'])) {
            $this->SetValueIfChanged('CarbonFilterSaturation', (int)$state['carbonFilterSaturation']);
        }
    }

    //==========================================================================
    // Actions
    //==========================================================================

    public function RequestAction(string $Ident, mixed $Value): void
    {
        $deviceId = $this->ReadPropertyString('DeviceID');
        if (empty($deviceId)) {
            $this->SLogInfo('Device ID not configured.');
            return;
        }

        switch ($Ident) {
            case 'DA_Watchdog':
                $this->DA_HandleWatchdog();
                break;
            case 'PowerOn':
                if ($Value && $this->GetCentralState('FireplaceActive')) {
                    echo "Fehler: Kamin ist aktiv! Dunstabzugshaube aus Sicherheitsgründen blockiert.\n";
                    $this->SLogInfo('Blockiert: PowerOn wegen aktivem Kamin.');
                    return;
                }
                $this->HandlePowerAction((bool)$Value);
                break;

            case 'Light':
                $this->HandleLightAction((bool)$Value);
                break;

            case 'AmbientLight':
                $this->HandleAmbientLightAction((bool)$Value);
                break;

            case 'VentilationStep':
                if ($Value > 0 && $this->GetCentralState('FireplaceActive')) {
                    echo "Fehler: Kamin ist aktiv! Lüftung aus Sicherheitsgründen blockiert.\n";
                    $this->SLogInfo('Blockiert: VentilationStep wegen aktivem Kamin.');
                    return;
                }
                $this->HandleVentilationAction((int)$Value);
                break;

            default:
                throw new Exception('Invalid Action: ' . $Ident);
        }
    }

    /**
     * Handles power on/off.
     */
    private function HandlePowerAction(bool $turnOn): void
    {
        if ($turnOn) {
            $actionData = ['powerOn' => true];
            $this->SLogInfo('Schalte Haube ein');
        } else {
            $actionData = ['powerOff' => true];
            $this->SLogInfo('Schalte Haube aus');
        }

        if ($this->Miele_SendAction($actionData)) {
            $this->SetValueIfChanged('PowerOn', $turnOn);
        }
    }

    /**
     * Smart Light Control: automatically powers on the hood if needed.
     */
    private function HandleLightAction(bool $turnOn): void
    {
        $this->SLogInfo('Schalte Licht: ' . ($turnOn ? 'An' : 'Aus'));

        // Check if light action is currently available
        $actions = $this->GetCachedActions();
        $lightAvailable = isset($actions['light']) && is_array($actions['light']) && !empty($actions['light']);

        if (!$lightAvailable && $turnOn) {
            // Hood is probably off – power it on first
            $this->SLogInfo('Licht nicht verfügbar – schalte Haube automatisch ein...');
            if (!$this->Miele_SendAction(['powerOn' => true])) {
                $this->SLogInfo('Fehler: Haube konnte nicht eingeschaltet werden');
                return;
            }
            $this->SetValueIfChanged('PowerOn', true);
        }

        // Now send the light command (Miele API: 1=On, 2=Off)
        if ($this->Miele_SendAction(['light' => $turnOn ? 1 : 2])) {
            $this->SetValueIfChanged('Light', $turnOn);
        }
    }

    /**
     * Smart Ambient Light Control: automatically powers on the hood if needed.
     */
    private function HandleAmbientLightAction(bool $turnOn): void
    {
        $this->SLogInfo('Schalte Stimmungslicht: ' . ($turnOn ? 'An' : 'Aus'));

        $actions = $this->GetCachedActions();
        $ambientAvailable = isset($actions['ambientLight']) && is_array($actions['ambientLight']) && !empty($actions['ambientLight']);

        if (!$ambientAvailable && $turnOn) {
            // Hood is probably off – power it on first
            $this->SLogInfo('Stimmungslicht nicht verfügbar – schalte Haube automatisch ein...');
            if (!$this->Miele_SendAction(['powerOn' => true])) {
                $this->SLogInfo('Fehler: Haube konnte nicht eingeschaltet werden');
                return;
            }
            $this->SetValueIfChanged('PowerOn', true);
        }

        if ($this->Miele_SendAction(['ambientLight' => $turnOn ? 1 : 2])) {
            $this->SetValueIfChanged('AmbientLight', $turnOn);
        }
    }

    /**
     * Handles ventilation step changes.
     */
    private function HandleVentilationAction(int $step): void
    {
        $this->SLogInfo('Setze Lüfterstufe: ' . $step);

        if ($this->Miele_SendAction(['ventilationStep' => $step])) {
            $this->SetValueIfChanged('VentilationStep', $step);
        }
    }

    //==========================================================================
    // Helper Methods
    //==========================================================================

    /**
     * Gets cached available actions (from SSE actions event).
     * Falls back to querying the Splitter if no cache exists.
     */
    private function GetCachedActions(): array
    {
        // Try local cache first
        $cached = $this->GetBuffer('DeviceActions');
        if (!empty($cached)) {
            $actions = json_decode($cached, true);
            if (is_array($actions)) {
                return $actions;
            }
        }

        // Fallback: query Splitter
        $deviceId = $this->ReadPropertyString('DeviceID');
        $payload = [
            'DataID'   => '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}',
            'Command'  => 'GetAvailableActions',
            'DeviceID' => $deviceId
        ];

        $result = $this->SendDataToParent(json_encode($payload));
        $actions = json_decode($result, true);

        return is_array($actions) ? $actions : [];
    }

    //==========================================================================
    // Configuration Form
    //==========================================================================

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
        },
        {
            "type": "Label",
            "caption": "ℹ️ Wenn das Licht gesteuert wird und die Haube aus ist, wird sie automatisch eingeschaltet."
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
