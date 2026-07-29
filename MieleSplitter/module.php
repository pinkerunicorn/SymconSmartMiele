<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Trait_SmartLog.php';

class MieleSplitter extends IPSModuleStrict
{
    use SmartLog_Trait;

    // Miele API Base URL
    private const API_BASE = 'https://api.mcs3.miele.com';
    private const SSE_URL = 'https://api.mcs3.miele.com/v1/devices/all/events';
    private const TOKEN_URL = 'https://api.mcs3.miele.com/thirdparty/token';

    // Token refresh 5 minutes before expiry
    private const TOKEN_REFRESH_MARGIN = 300;
    // Token check interval (every 60 seconds)
    private const TOKEN_CHECK_INTERVAL = 60000;

    public function Create(): void
    {
        parent::Create();

        // Self-healing for corrupted CustomPresentations
        foreach (@IPS_GetChildrenIDs($this->InstanceID) as $childID) {
            if (@IPS_VariableExists($childID)) {
                @IPS_SetVariableCustomPresentation($childID, []);
            }
        }

        // OAuth2 Credentials
        $this->RegisterPropertyString('ClientID', '');
        $this->RegisterPropertyString('ClientSecret', '');
        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyString('Country', 'de-DE');

        // OAuth2 Token Storage
        $this->RegisterAttributeString('AccessToken', '');
        $this->RegisterAttributeString('RefreshToken', '');
        $this->RegisterAttributeInteger('TokenExpires', 0);

        // Token refresh timer (replaces polling timer)
        $this->RegisterTimer('SM_TokenRefresh', 0, 'SM_CheckTokenRefresh($_IPS[\'TARGET\']);');

        // Connection monitoring variable
        $this->RegisterVariableString('SSEStatus', 'SSE Verbindung', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Network'
        ], 10);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        if ($this->ReadPropertyString('ClientID') != '' && $this->ReadPropertyString('Username') != '') {
            // Start token refresh timer
            $this->SetTimerInterval('SM_TokenRefresh', self::TOKEN_CHECK_INTERVAL);

            // Initial token acquisition and SSE setup
            $token = $this->GetToken();
            if ($token) {
                $this->UpdateSSEClientConfig($token);
                $this->SetStatus(102); // Active
                $this->SetValue('SSEStatus', 'Verbunden');
            } else {
                $this->SetStatus(200); // Auth failed
                $this->SetValue('SSEStatus', 'Authentifizierung fehlgeschlagen');
            }
        } else {
            $this->SetTimerInterval('SM_TokenRefresh', 0);
            $this->SetStatus(104); // Inactive
            $this->SetValue('SSEStatus', 'Nicht konfiguriert');
        }
    }

    //==========================================================================
    // SSE Event Handling (receives data from SSE Client I/O)
    //==========================================================================

    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        if (!is_array($data) || ($data['DataID'] ?? '') !== '{5A709184-B602-D394-227F-207611A33BDF}') {
            return '';
        }

        $eventType = strtolower($data['Event'] ?? '');
        $eventData = $data['Data'] ?? '';

        $this->SetBuffer('LastSSEReceive', (string)time());

        switch ($eventType) {
            case 'ping':
                // Heartbeat – connection is alive
                $this->SendDebug('SSE', 'PING received', 0);
                break;

            case 'devices':
                $this->SendDebug('SSE', 'DEVICES event, length: ' . strlen($eventData), 0);
                $devices = json_decode($eventData, true);
                if (is_array($devices)) {
                    // Forward device state data to children
                    $payload = [
                        'DataID'  => '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}',
                        'Type'    => 'DeviceUpdate',
                        'Devices' => $devices
                    ];
                    $this->SendDataToChildren(json_encode($payload));
                    $this->SetStatus(102);
                    $this->SetValue('SSEStatus', 'Verbunden (letztes Update: ' . date('H:i:s') . ')');
                }
                break;

            case 'actions':
                $this->SendDebug('SSE', 'ACTIONS event, length: ' . strlen($eventData), 0);
                $actions = json_decode($eventData, true);
                if (is_array($actions)) {
                    // Cache actions for GetAvailableActions queries
                    $this->SetBuffer('AvailableActions', json_encode($actions));
                    // Also forward to children so they can update their local cache
                    $payload = [
                        'DataID'  => '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}',
                        'Type'    => 'ActionsUpdate',
                        'Actions' => $actions
                    ];
                    $this->SendDataToChildren(json_encode($payload));
                }
                break;

            default:
                $this->SendDebug('SSE', 'Unknown event type: ' . $eventType . ' Data: ' . substr($eventData, 0, 200), 0);
                break;
        }

        return '';
    }

    //==========================================================================
    // SSE Client Configuration
    //==========================================================================

    /**
     * Provides default configuration for the SSE Client I/O parent instance.
     * Called automatically by IP-Symcon when creating the parent.
     */
    public function GetConfigurationForParent(): string
    {
        $token = $this->ReadAttributeString('AccessToken');
        $country = $this->ReadPropertyString('Country');
        if (empty($country)) {
            $country = 'de-DE';
        }

        $headers = [
            ['Name' => 'Authorization', 'Value' => 'Bearer ' . $token],
            ['Name' => 'Accept', 'Value' => 'text/event-stream'],
            ['Name' => 'Accept-Language', 'Value' => $country]
        ];

        return json_encode([
            'URL'     => self::SSE_URL,
            'Headers' => json_encode($headers),
            'Active'  => !empty($token)
        ]);
    }

    /**
     * Updates the SSE Client I/O instance with new Bearer token after refresh.
     */
    private function UpdateSSEClientConfig(string $token): void
    {
        $instance = @IPS_GetInstance($this->InstanceID);
        if (!$instance || $instance['ConnectionID'] <= 0) {
            $this->SendDebug('SSE Config', 'No parent I/O instance connected', 0);
            return;
        }

        $parentId = $instance['ConnectionID'];
        $country = $this->ReadPropertyString('Country');
        if (empty($country)) {
            $country = 'de-DE';
        }

        $headers = [
            ['Name' => 'Authorization', 'Value' => 'Bearer ' . $token],
            ['Name' => 'Accept', 'Value' => 'text/event-stream'],
            ['Name' => 'Accept-Language', 'Value' => $country]
        ];

        @IPS_SetProperty($parentId, 'URL', self::SSE_URL);
        @IPS_SetProperty($parentId, 'Headers', json_encode($headers));
        @IPS_SetProperty($parentId, 'Active', true);
        @IPS_ApplyChanges($parentId);

        $this->SendDebug('SSE Config', 'Updated SSE Client headers with new token', 0);
    }

    //==========================================================================
    // OAuth2 Token Management
    //==========================================================================

    /**
     * Gets a valid access token. Tries refresh token first, falls back to password auth.
     */
    private function GetToken(): string|false
    {
        $token = $this->ReadAttributeString('AccessToken');
        $expires = $this->ReadAttributeInteger('TokenExpires');

        // Token still valid (with margin)
        if ($token != '' && $expires > time() + self::TOKEN_REFRESH_MARGIN) {
            return $token;
        }

        // Try refresh token first
        $refreshToken = $this->ReadAttributeString('RefreshToken');
        if (!empty($refreshToken)) {
            $result = $this->RequestToken('refresh_token', $refreshToken);
            if ($result !== false) {
                return $result;
            }
            $this->SendDebug('Auth', 'Refresh token failed, falling back to password auth', 0);
        }

        // Fallback: password auth (initial login)
        return $this->RequestTokenViaPassword();
    }

    /**
     * Requests a new token using refresh_token grant.
     */
    private function RequestToken(string $grantType, string $refreshToken): string|false
    {
        $postData = http_build_query([
            'client_id'     => $this->ReadPropertyString('ClientID'),
            'client_secret' => $this->ReadPropertyString('ClientSecret'),
            'grant_type'    => $grantType,
            'refresh_token' => $refreshToken
        ]);

        return $this->ExecuteTokenRequest($postData);
    }

    /**
     * Requests a new token using password grant (initial authentication).
     */
    private function RequestTokenViaPassword(): string|false
    {
        $clientId = $this->ReadPropertyString('ClientID');
        $username = $this->ReadPropertyString('Username');

        if (empty($clientId) || empty($username)) {
            $this->SendDebug('Auth', 'Credentials missing', 0);
            return false;
        }

        $postData = http_build_query([
            'client_id'     => $clientId,
            'client_secret' => $this->ReadPropertyString('ClientSecret'),
            'grant_type'    => 'password',
            'username'      => $username,
            'password'      => $this->ReadPropertyString('Password'),
            'state'         => 'token',
            'redirect_uri'  => '/v1/devices',
            'vg'            => $this->ReadPropertyString('Country')
        ]);

        return $this->ExecuteTokenRequest($postData);
    }

    /**
     * Executes the actual token HTTP request.
     */
    private function ExecuteTokenRequest(string $postData): string|false
    {
        $ch = curl_init(self::TOKEN_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json; charset=utf-8',
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $result = curl_exec($ch);
        if ($result === false) {
            $this->SLog('ERROR', 'Token-Anfrage fehlgeschlagen', curl_error($ch));
            curl_close($ch);
            return false;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->SendDebug('Auth', 'HTTP Code: ' . $httpCode . ' Result: ' . $result, 0);

        if ($httpCode == 200 && $result) {
            $data = json_decode($result, true);
            if (isset($data['access_token'])) {
                $this->WriteAttributeString('AccessToken', $data['access_token']);
                if (isset($data['refresh_token'])) {
                    $this->WriteAttributeString('RefreshToken', $data['refresh_token']);
                }
                $this->WriteAttributeInteger('TokenExpires', time() + ($data['expires_in'] ?? 3600));
                $this->SetStatus(102);
                return $data['access_token'];
            }
        }

        $this->SLog('ERROR', 'Token-Anfrage fehlgeschlagen', 'HTTP Code: ' . $httpCode);
        $this->SetStatus(200); // Auth failed
        return false;
    }

    /**
     * Timer callback: checks if token needs refresh and updates SSE Client headers.
     */
    public function CheckTokenRefresh(): void
    {
        $expires = $this->ReadAttributeInteger('TokenExpires');

        // Token expires soon or already expired
        if ($expires <= time() + self::TOKEN_REFRESH_MARGIN) {
            $this->SendDebug('TokenRefresh', 'Token expiring, refreshing...', 0);
            $token = $this->GetToken();
            if ($token) {
                $this->UpdateSSEClientConfig($token);
                $this->SLogInfo('OAuth2 Token erfolgreich erneuert');
            } else {
                $this->SLogError('OAuth2 Token-Erneuerung fehlgeschlagen');
                $this->SetValue('SSEStatus', 'Token-Erneuerung fehlgeschlagen');
            }
        }
    }

    //==========================================================================
    // REST API for Actions (PUT requests still via curl)
    //==========================================================================

    /**
     * Executes a GET request to the Miele API.
     */
    public function ApiGet(string $endpoint): array|false
    {
        $token = $this->GetToken();
        if (!$token) {
            return false;
        }

        $url = self::API_BASE . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json; charset=utf-8',
            'Authorization: Bearer ' . $token,
            'Accept-Language: ' . $this->ReadPropertyString('Country')
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $result = curl_exec($ch);
        if ($result === false) {
            $this->SLog('ERROR', 'API-Anfrage fehlgeschlagen', curl_error($ch));
            curl_close($ch);
            return false;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->SendDebug('ApiGet', 'Endpoint: ' . $endpoint . ' HTTP Code: ' . $httpCode, 0);

        if ($httpCode == 200 && $result) {
            return json_decode($result, true);
        }
        return false;
    }

    /**
     * Executes a PUT action on a device via the Miele API.
     */
    public function ExecuteAction(string $deviceId, array $actionData): bool
    {
        $token = $this->GetToken();
        if (!$token) {
            return false;
        }

        $url = self::API_BASE . '/v1/devices/' . urlencode($deviceId) . '/actions';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($actionData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: */*',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
            'Accept-Language: ' . $this->ReadPropertyString('Country')
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $result = curl_exec($ch);
        if ($result === false) {
            $this->SLog('ERROR', 'API-Anfrage fehlgeschlagen', curl_error($ch));
            curl_close($ch);
            return false;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->SendDebug('ExecuteAction', 'Device: ' . $deviceId . ' Payload: ' . json_encode($actionData) . ' HTTP Code: ' . $httpCode . ' Result: ' . $result, 0);

        if ($httpCode == 200 || $httpCode == 204) {
            return true;
        }

        $resultData = @json_decode($result, true);
        if ($httpCode == 400 && is_array($resultData) && isset($resultData['message']) && strpos($resultData['message'], 'is not available for device') !== false) {
            $this->SLog('WARNING', 'Aktion aktuell nicht verfügbar (Miele API)', $resultData['message']);
            return false;
        }

        $this->SLog('ERROR', 'Fehler beim Ausführen der Aktion (Miele API)', 'HTTP Code: ' . $httpCode . ' | Result: ' . $result);
        return false;
    }

    /**
     * Returns cached available actions for a specific device.
     */
    public function GetAvailableActions(string $deviceId): array
    {
        $actionsJson = $this->GetBuffer('AvailableActions');
        if (empty($actionsJson)) {
            return [];
        }

        $allActions = json_decode($actionsJson, true);
        if (!is_array($allActions)) {
            return [];
        }

        return $allActions[$deviceId] ?? [];
    }

    //==========================================================================
    // Child Device Communication
    //==========================================================================

    public function ForwardData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        if (!is_array($data)) {
            return '{}';
        }

        if (($data['DataID'] ?? '') == '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}') {
            if (isset($data['Command'])) {
                switch ($data['Command']) {
                    case 'ExecuteAction':
                        return json_encode($this->ExecuteAction($data['DeviceID'] ?? '', $data['ActionData'] ?? []));
                    case 'ApiGet':
                        return json_encode($this->ApiGet($data['Endpoint'] ?? ''));
                    case 'GetAvailableActions':
                        return json_encode($this->GetAvailableActions($data['DeviceID'] ?? ''));
                }
            }
        }
        return '{}';
    }

    //==========================================================================
    // UI Actions
    //==========================================================================

    /**
     * Tests the connection and performs initial token acquisition.
     */
    public function TestConnection(): void
    {
        $token = $this->GetToken();
        if ($token) {
            $this->UpdateSSEClientConfig($token);
            echo "Authentifizierung erfolgreich! SSE-Verbindung wird aufgebaut.\n";
        } else {
            echo "Authentifizierung fehlgeschlagen. Bitte Zugangsdaten prüfen.\n";
        }
    }

    /**
     * Forces a reconnect of the SSE stream.
     */
    public function ReconnectSSE(): void
    {
        $token = $this->GetToken();
        if ($token) {
            $this->UpdateSSEClientConfig($token);
            $this->SetValue('SSEStatus', 'Reconnect...');
            echo "SSE-Verbindung wird neu aufgebaut.\n";
        } else {
            echo "Kein gültiger Token verfügbar.\n";
        }
    }

    //==========================================================================
    // Logging
    //==========================================================================

    protected function LogMessage(string $Message, int $Type): bool
    {
        $this->SLog('INFO', $Message);
        IPS_LogMessage('SmartVillaKunterbunt', 'MieleSplitter: ' . $Message);
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
            "caption": "Hey! Hier verbinden wir uns mit der Miele Cloud via Echtzeit-SSE-Stream. Trag einfach deine Zugangsdaten und die API-Schlüssel ein."
        },
        {
            "type": "RowLayout",
            "items": [
                {
                    "type": "ValidationTextBox",
                    "name": "ClientID",
                    "caption": "Client ID"
                },
                {
                    "type": "ValidationTextBox",
                    "name": "ClientSecret",
                    "caption": "Client Secret"
                }
            ]
        },
        {
            "type": "RowLayout",
            "items": [
                {
                    "type": "ValidationTextBox",
                    "name": "Username",
                    "caption": "Miele Username (Email)"
                }
            ]
        },
        {
            "type": "PasswordTextBox",
            "name": "Password",
            "caption": "Miele Password"
        },
        {
            "type": "Select",
            "name": "Country",
            "caption": "Country",
            "options": [
                {
                    "caption": "Deutschland",
                    "value": "de-DE"
                },
                {
                    "caption": "Österreich",
                    "value": "de-AT"
                },
                {
                    "caption": "Schweiz",
                    "value": "de-CH"
                }
            ]
        },
        {
            "type": "Label",
            "caption": "ℹ️ Die Verbindung läuft über Server-Sent Events (SSE) – Statusänderungen kommen in Echtzeit, ohne Polling."
        }
    ],
    "actions": [
        {
            "type": "Button",
            "caption": "Verbindung testen",
            "onClick": "SM_TestConnection($id);",
            "icon": "Play"
        },
        {
            "type": "Button",
            "caption": "SSE Reconnect",
            "onClick": "SM_ReconnectSSE($id);",
            "icon": "Repeat"
        }
    ],
    "status": [
        {
            "code": 102,
            "icon": "active",
            "caption": "SSE Verbindung aktiv"
        },
        {
            "code": 200,
            "icon": "error",
            "caption": "Authentifizierung fehlgeschlagen"
        },
        {
            "code": 201,
            "icon": "error",
            "caption": "API Fehler"
        },
        {
            "code": 202,
            "icon": "error",
            "caption": "SSE Verbindung getrennt"
        }
    ]
}
EOT;
    }
}
