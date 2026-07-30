<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Trait_SmartLog.php';
require_once __DIR__ . '/../libs/Trait_CentralStateAware.php';

class MieleHood extends IPSModuleStrict
{
    use SmartLog_Trait;
    use CentralStateAware_Trait;

    public function Create(): void
    {
        parent::Create();

        // Self-healing for corrupted CustomPresentations
        foreach (@IPS_GetChildrenIDs($this->InstanceID) as $childID) {
            if (@IPS_VariableExists($childID)) {
                @IPS_SetVariableCustomPresentation($childID, []);
            }
        }

        $this->RegisterPropertyString('DeviceID', '');

        // Variables
        $this->RegisterVariableInteger('PowerSupply', 'Spannungsversorgung', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Power'
        ], 5);

        $this->RegisterVariableString('StatusText', 'Status', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Information'
        ], 10);

        $this->RegisterVariableBoolean('PowerOn', 'Eingeschaltet', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Power'
        ], 15);
        $this->EnableAction('PowerOn');

        $this->RegisterVariableBoolean('Light', 'Licht', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Bulb'
        ], 20);
        $this->EnableAction('Light');

        $this->RegisterVariableBoolean('AmbientLight', 'Stimmungslicht', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Bulb'
        ], 25);
        $this->EnableAction('AmbientLight');

        // Lüfterstufe als Dropdown
        $this->RegisterVariableInteger('VentilationStep', 'Lüfterstufe', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Ventilator'
        ], 30);
        $this->EnableAction('VentilationStep');

        $this->RegisterVariableBoolean('SignalInfo', 'Hinweis vorhanden (z.B. Filter)', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Warning'
        ], 40);
        $this->RegisterVariableBoolean('SignalFailure', 'Fehler erkannt', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Alert'
        ], 41);

        $this->RegisterVariableInteger('GreaseFilterSaturation', 'Fettfilter-Sättigung', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON'         => 'Gauge',
            'SUFFIX'       => '%'
        ], 50);

        $this->RegisterVariableInteger('CarbonFilterSaturation', 'Kohlefilter-Sättigung', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON'         => 'Gauge',
            'SUFFIX'       => '%'
        ], 51);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        
        $this->SubscribeToCentralStates(['FireplaceActive']);

        $powerSupplyOptions = json_encode([
            ['Value' => 0, 'Caption' => 'Unbekannt', 'IconValue' => 'Power', 'IconActive' => true, 'ColorActive' => false, 'ColorDisplay' => -1, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => -1],
            ['Value' => 1, 'Caption' => 'Eingeschaltet', 'IconValue' => 'Power', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0x00CC00, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0x00CC00],
            ['Value' => 2, 'Caption' => 'Ausgeschaltet', 'IconValue' => 'Power', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFF0000, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFF0000]
        ]);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('PowerSupply'), [
            'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
            'ICON' => 'Power',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => $powerSupplyOptions
        ]);

        $powerOnOptions = json_encode([
            ['Value' => false, 'Caption' => 'Aus', 'IconValue' => 'Power', 'IconActive' => true, 'ColorActive' => false, 'ColorDisplay' => -1, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => -1],
            ['Value' => true, 'Caption' => 'Ein', 'IconValue' => 'Power', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0x00CC00, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0x00CC00]
        ]);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('PowerOn'), [
            'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
            'ICON' => 'Power',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => $powerOnOptions
        ]);

        $lightOptions = json_encode([
            ['Value' => false, 'Caption' => 'Aus', 'IconValue' => 'Light', 'IconActive' => true, 'ColorActive' => false, 'ColorDisplay' => -1, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => -1],
            ['Value' => true, 'Caption' => 'An', 'IconValue' => 'Light', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFFCC00, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFFCC00]
        ]);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Light'), [
            'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
            'ICON' => 'Light',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => $lightOptions
        ]);

        $ambientLightOptions = json_encode([
            ['Value' => false, 'Caption' => 'Aus', 'IconValue' => 'Paintbrush', 'IconActive' => true, 'ColorActive' => false, 'ColorDisplay' => -1, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => -1],
            ['Value' => true, 'Caption' => 'An', 'IconValue' => 'Paintbrush', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xCC00FF, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xCC00FF]
        ]);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('AmbientLight'), [
            'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
            'ICON' => 'Paintbrush',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => $ambientLightOptions
        ]);

        $signalInfoOptions = json_encode([
            ['Value' => false, 'Caption' => 'Kein Hinweis', 'IconValue' => 'Information', 'IconActive' => true, 'ColorActive' => false, 'ColorDisplay' => -1, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => -1],
            ['Value' => true, 'Caption' => 'Hinweis!', 'IconValue' => 'Warning', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFFA500, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFFA500]
        ]);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('SignalInfo'), [
            'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
            'ICON' => 'Warning',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => $signalInfoOptions
        ]);

        $signalFailureOptions = json_encode([
            ['Value' => false, 'Caption' => 'OK', 'IconValue' => 'Ok', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0x00CC00, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0x00CC00],
            ['Value' => true, 'Caption' => 'Fehler!', 'IconValue' => 'Alert', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFF0000, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFF0000]
        ]);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('SignalFailure'), [
            'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
            'ICON' => 'Alert',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => $signalFailureOptions
        ]);

        // CustomPresentation: Lüfterstufe Dropdown
        $ventOptions = json_encode([
            ['Value' => 0, 'Caption' => 'Aus', 'IconValue' => 'Ventilator', 'IconActive' => true, 'ColorActive' => false, 'ColorDisplay' => -1, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => -1],
            ['Value' => 1, 'Caption' => 'Stufe 1', 'IconValue' => 'Ventilator', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0x00CC00, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0x00CC00],
            ['Value' => 2, 'Caption' => 'Stufe 2', 'IconValue' => 'Ventilator', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFFAA00, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFFAA00],
            ['Value' => 3, 'Caption' => 'Stufe 3', 'IconValue' => 'Ventilator', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFF6600, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFF6600],
            ['Value' => 4, 'Caption' => 'Booster', 'IconValue' => 'Ventilator', 'IconActive' => true, 'ColorActive' => true, 'ColorDisplay' => 0xFF0000, 'ContentColorActive' => false, 'ContentColorDisplay' => -1, 'ContentColorValue' => -1, 'ColorValue' => 0xFF0000]
        ]);
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('VentilationStep'), [
            'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
            'ICON' => 'Ventilator',
            'COLOR' => -1,
            'CONTENT_COLOR' => -1,
            'DISPLAY_TYPE' => 0,
            'PREVIEW_STYLE' => 1,
            'SHOW_PREVIEW' => true,
            'OPTIONS' => $ventOptions
        ]);

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

    private function OnCentralStateChanged(string $stateName, mixed $newValue): void
    {
        if ($stateName === 'FireplaceActive' && $newValue) {
            $this->Log('Kamin ist aktiv! Schalte Dunstabzugshaube zur Sicherheit aus.');
            if ($this->GetValue('PowerOn')) {
                $this->RequestAction('PowerOn', false);
            }
        }
    }

    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        if (!is_array($data) || ($data['DataID'] ?? '') !== '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}') {
            return '';
        }

        $deviceId = $this->ReadPropertyString('DeviceID');
        if (empty($deviceId)) {
            return '';
        }

        $type = $data['Type'] ?? '';

        // Handle device state updates
        if (($type === 'DeviceUpdate' || !isset($data['Type'])) && isset($data['Devices'][$deviceId])) {
            $this->ProcessDeviceData($data['Devices'][$deviceId]);
        }

        // Handle actions updates – cache locally
        if ($type === 'ActionsUpdate' && isset($data['Actions'][$deviceId])) {
            $this->SetBuffer('DeviceActions', json_encode($data['Actions'][$deviceId]));
            $this->SendDebug('Actions', 'Cached actions: ' . json_encode($data['Actions'][$deviceId]), 0);
        }

        return '';
    }

    //==========================================================================
    // Device Data Processing
    //==========================================================================

    private function ProcessDeviceData(array $deviceData): void
    {
        if (!isset($deviceData['state'])) {
            return;
        }

        $state = $deviceData['state'];

        if (isset($state['status']['value_localized'])) {
            $this->SetValue('StatusText', (string)$state['status']['value_localized']);
        }

        // Power state: off (1) = false, on (2) = true, and others = true
        if (isset($state['status']['value_raw'])) {
            $statusRaw = (int)$state['status']['value_raw'];
            $this->SetValue('PowerOn', $statusRaw !== 1);
        }

        // Signal-Flags (Hinweis = z.B. Fettfilter reinigen)
        if (isset($state['signalInfo'])) {
            $this->SetValue('SignalInfo', (bool)$state['signalInfo']);
        }
        if (isset($state['signalFailure'])) {
            $this->SetValue('SignalFailure', (bool)$state['signalFailure']);
        }

        // Light (Miele API: 1=On, 2=Off)
        if (isset($state['light'])) {
            $this->SetValue('Light', (bool)($state['light'] == 1));
        }

        // Ambient Light (Miele API: 1=On, 2=Off) – may not exist on all models
        if (isset($state['ambientLight'])) {
            $this->SetValue('AmbientLight', (bool)($state['ambientLight'] == 1));
        }

        // VentilationStep
        if (isset($state['ventilationStep']['value_raw'])) {
            $this->SetValue('VentilationStep', (int)$state['ventilationStep']['value_raw']);
        }

        // PowerSupply
        if (isset($state['powerSupply']['value_raw'])) {
            $this->SetValue('PowerSupply', (int)$state['powerSupply']['value_raw']);
        }

        // GreaseFilterSaturation
        if (isset($state['greaseFilterSaturation'])) {
            $this->SetValue('GreaseFilterSaturation', (int)$state['greaseFilterSaturation']);
        }

        // CarbonFilterSaturation
        if (isset($state['carbonFilterSaturation'])) {
            $this->SetValue('CarbonFilterSaturation', (int)$state['carbonFilterSaturation']);
        }
    }

    //==========================================================================
    // Actions
    //==========================================================================

    public function RequestAction(string $Ident, mixed $Value): void
    {
        $deviceId = $this->ReadPropertyString('DeviceID');
        if (empty($deviceId)) {
            $this->Log('Device ID not configured.');
            return;
        }

        switch ($Ident) {
            case 'PowerOn':
                if ($Value && $this->GetCentralState('FireplaceActive')) {
                    echo "Fehler: Kamin ist aktiv! Dunstabzugshaube aus Sicherheitsgründen blockiert.\n";
                    $this->Log('Blockiert: PowerOn wegen aktivem Kamin.');
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
                    $this->Log('Blockiert: VentilationStep wegen aktivem Kamin.');
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
            $this->Log('Schalte Haube ein');
        } else {
            $actionData = ['powerOff' => true];
            $this->Log('Schalte Haube aus');
        }

        if ($this->SendAction($actionData)) {
            $this->SetValue('PowerOn', $turnOn);
        }
    }

    /**
     * Smart Light Control: automatically powers on the hood if needed.
     */
    private function HandleLightAction(bool $turnOn): void
    {
        $this->Log('Schalte Licht: ' . ($turnOn ? 'An' : 'Aus'));

        // Check if light action is currently available
        $actions = $this->GetCachedActions();
        $lightAvailable = isset($actions['light']) && is_array($actions['light']) && !empty($actions['light']);

        if (!$lightAvailable && $turnOn) {
            // Hood is probably off – power it on first
            $this->Log('Licht nicht verfügbar – schalte Haube automatisch ein...');
            if (!$this->SendAction(['powerOn' => true])) {
                $this->Log('Fehler: Haube konnte nicht eingeschaltet werden');
                return;
            }
            $this->SetValue('PowerOn', true);
            // Wait for the hood to become ready
            IPS_Sleep(2000);
        }

        // Now send the light command (Miele API: 1=On, 2=Off)
        if ($this->SendAction(['light' => $turnOn ? 1 : 2])) {
            $this->SetValue('Light', $turnOn);
        }
    }

    /**
     * Smart Ambient Light Control: automatically powers on the hood if needed.
     */
    private function HandleAmbientLightAction(bool $turnOn): void
    {
        $this->Log('Schalte Stimmungslicht: ' . ($turnOn ? 'An' : 'Aus'));

        $actions = $this->GetCachedActions();
        $ambientAvailable = isset($actions['ambientLight']) && is_array($actions['ambientLight']) && !empty($actions['ambientLight']);

        if (!$ambientAvailable && $turnOn) {
            // Hood is probably off – power it on first
            $this->Log('Stimmungslicht nicht verfügbar – schalte Haube automatisch ein...');
            if (!$this->SendAction(['powerOn' => true])) {
                $this->Log('Fehler: Haube konnte nicht eingeschaltet werden');
                return;
            }
            $this->SetValue('PowerOn', true);
            IPS_Sleep(2000);
        }

        if ($this->SendAction(['ambientLight' => $turnOn ? 1 : 2])) {
            $this->SetValue('AmbientLight', $turnOn);
        }
    }

    /**
     * Handles ventilation step changes.
     */
    private function HandleVentilationAction(int $step): void
    {
        $this->Log('Setze Lüfterstufe: ' . $step);

        if ($this->SendAction(['ventilationStep' => $step])) {
            $this->SetValue('VentilationStep', $step);
        }
    }

    //==========================================================================
    // Helper Methods
    //==========================================================================

    /**
     * Sends an action to the Splitter for execution.
     */
    private function SendAction(array $actionData): bool
    {
        $deviceId = $this->ReadPropertyString('DeviceID');
        $payload = [
            'DataID'     => '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}',
            'Command'    => 'ExecuteAction',
            'DeviceID'   => $deviceId,
            'ActionData' => $actionData
        ];

        $result = $this->SendDataToParent(json_encode($payload));
        $success = json_decode($result, true);

        if (!$success) {
            $this->Log('Fehler beim Ausführen der Aktion: ' . json_encode($actionData));
        }

        return (bool)$success;
    }

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

    /**
     * Manual device update via REST API.
     */
    public function UpdateDevice(): void
    {
        $deviceId = $this->ReadPropertyString('DeviceID');
        if (empty($deviceId)) {
            echo "Fehler: Bitte zuerst eine Device ID eintragen.\n";
            return;
        }

        $payload = [
            'DataID'  => '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}',
            'Command' => 'ApiGet',
            'Endpoint' => '/v1/devices/' . urlencode($deviceId) . '/state'
        ];

        $result = $this->SendDataToParent(json_encode($payload));
        $state = json_decode($result, true);

        if ($state && is_array($state) && !isset($state['message'])) {
            $this->ProcessDeviceData(['state' => $state]);
            echo "Gerät erfolgreich aktualisiert!\n";
        } else {
            if (isset($state['message'])) {
                $this->SLog('ERROR', 'Miele Update-Fehler', $state['message'] ?? 'Unbekannter Fehler');
            } else {
                echo "Fehler beim Update: Konnte keine Daten abrufen.\n";
            }
        }
    }

    protected function Log(string $text): void
    {
        $this->SLog('INFO', $text);
    }

    protected function LogMessage(string $Message, int $Type): bool
    {
        $this->SLog('INFO', $Message);
        IPS_LogMessage('SmartVillaKunterbunt', 'MieleHood: ' . $Message);
        return true;
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
    ]
}
EOT;
    }
}
