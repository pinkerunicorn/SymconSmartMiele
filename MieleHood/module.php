<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Trait_SmartLog.php';
require_once __DIR__ . '/../libs/Trait_CentralStateAware.php';
require_once __DIR__ . '/../libs/Trait_DeviceAvailability.php';

class MieleHood extends IPSModuleStrict
{
    use SmartLog_Trait;
    use CentralStateAware_Trait;
    use DeviceAvailability_Trait;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('DeviceID', '');

        $this->DA_RegisterAvailability(900);

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
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
            'ICON' => 'Power'
        ], 15);
        $this->EnableAction('PowerOn');

        $this->RegisterVariableBoolean('Light', 'Licht', [
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
            'ICON' => 'Bulb'
        ], 20);
        $this->EnableAction('Light');

        $this->RegisterVariableBoolean('AmbientLight', 'Stimmungslicht', [
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
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

        if (empty($this->ReadPropertyString('DeviceID'))) {
            $this->SetStatus(104);
            return;
        }
        
        $this->SubscribeToCentralStates(['FireplaceActive']);

        
        if (!IPS_VariableProfileExists('Miele.PowerSupply')) {
            IPS_CreateVariableProfile('Miele.PowerSupply', 1);
        }
        IPS_SetVariableCustomProfile($this->GetIDForIdent('PowerSupply'), 'Miele.PowerSupply');
        IPS_SetVariableProfileAssociation('Miele.PowerSupply', 0, 'Unbekannt', 'Power', -1);
        IPS_SetVariableProfileAssociation('Miele.PowerSupply', 1, 'Eingeschaltet', 'Power', 0x00CC00);
        IPS_SetVariableProfileAssociation('Miele.PowerSupply', 2, 'Ausgeschaltet', 'Power', 0xFF0000);

        if (!IPS_VariableProfileExists('Miele.PowerOn')) {
            IPS_CreateVariableProfile('Miele.PowerOn', 0);
            IPS_SetVariableProfileAssociation('Miele.PowerOn', false, 'Aus', 'Power', -1);
            IPS_SetVariableProfileAssociation('Miele.PowerOn', true, 'Ein', 'Power', 0x00CC00);
        }
        IPS_SetVariableCustomProfile($this->GetIDForIdent('PowerOn'), 'Miele.PowerOn');

        if (!IPS_VariableProfileExists('Miele.Light')) {
            IPS_CreateVariableProfile('Miele.Light', 0);
            IPS_SetVariableProfileAssociation('Miele.Light', false, 'Aus', 'Light', -1);
            IPS_SetVariableProfileAssociation('Miele.Light', true, 'An', 'Light', 0xFFCC00);
        }
        IPS_SetVariableCustomProfile($this->GetIDForIdent('Light'), 'Miele.Light');

        if (!IPS_VariableProfileExists('Miele.AmbientLight')) {
            IPS_CreateVariableProfile('Miele.AmbientLight', 0);
            IPS_SetVariableProfileAssociation('Miele.AmbientLight', false, 'Aus', 'Paintbrush', -1);
            IPS_SetVariableProfileAssociation('Miele.AmbientLight', true, 'An', 'Paintbrush', 0xCC00FF);
        }
        IPS_SetVariableCustomProfile($this->GetIDForIdent('AmbientLight'), 'Miele.AmbientLight');

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
        
        if (!IPS_VariableProfileExists('Miele.VentilationStep')) {
            IPS_CreateVariableProfile('Miele.VentilationStep', 1);
        }
        IPS_SetVariableCustomProfile($this->GetIDForIdent('VentilationStep'), 'Miele.VentilationStep');
        IPS_SetVariableProfileAssociation('Miele.VentilationStep', 0, 'Aus', 'Ventilator', -1);
        IPS_SetVariableProfileAssociation('Miele.VentilationStep', 1, 'Stufe 1', 'Ventilator', 0x00CC00);
        IPS_SetVariableProfileAssociation('Miele.VentilationStep', 2, 'Stufe 2', 'Ventilator', 0xFFAA00);
        IPS_SetVariableProfileAssociation('Miele.VentilationStep', 3, 'Stufe 3', 'Ventilator', 0xFF6600);
        IPS_SetVariableProfileAssociation('Miele.VentilationStep', 4, 'Booster', 'Ventilator', 0xFF0000);

        // Aktionen nach CustomPresentation re-aktivieren
        $this->EnableAction('PowerOn');
        $this->EnableAction('Light');
        $this->EnableAction('AmbientLight');
        $this->EnableAction('VentilationStep');

        $this->DA_ApplyPresentation();
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
            $this->DA_SetAvailable(true);
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

        if ($this->SendAction($actionData)) {
            $this->SetValue('PowerOn', $turnOn);
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
            if (!$this->SendAction(['powerOn' => true])) {
                $this->SLogInfo('Fehler: Haube konnte nicht eingeschaltet werden');
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
        $this->SLogInfo('Schalte Stimmungslicht: ' . ($turnOn ? 'An' : 'Aus'));

        $actions = $this->GetCachedActions();
        $ambientAvailable = isset($actions['ambientLight']) && is_array($actions['ambientLight']) && !empty($actions['ambientLight']);

        if (!$ambientAvailable && $turnOn) {
            // Hood is probably off – power it on first
            $this->SLogInfo('Stimmungslicht nicht verfügbar – schalte Haube automatisch ein...');
            if (!$this->SendAction(['powerOn' => true])) {
                $this->SLogInfo('Fehler: Haube konnte nicht eingeschaltet werden');
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
        $this->SLogInfo('Setze Lüfterstufe: ' . $step);

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
            $this->SLogInfo('Fehler beim Ausführen der Aktion: ' . json_encode($actionData));
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
            $this->DA_SetAvailable(true);
            echo "Gerät erfolgreich aktualisiert!\n";
        } else {
            $this->DA_SetAvailable(false, 'API-Fehler beim manuellen Update');
            if (isset($state['message'])) {
                $this->SLog('ERROR', 'Miele Update-Fehler', $state['message'] ?? 'Unbekannter Fehler');
            } else {
                echo "Fehler beim Update: Konnte keine Daten abrufen.\n";
            }
        }
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
