<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Trait_SmartLog.php';
require_once __DIR__ . '/../libs/Trait_SmartHttp.php';
require_once __DIR__ . '/../libs/Trait_DeviceAvailability.php';

class MieleSplitter extends IPSModuleStrict
{
    use SmartLog_Trait;
    use SmartHttp_Trait;
    use DeviceAvailability_Trait;

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


        $this->DA_RegisterAvailability(900);
        $this->DA_RegisterWatchdog();

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
        $this->DA_ApplyPresentation();

        if (empty($this->ReadPropertyString('ClientID')) || empty($this->ReadPropertyString('Username'))) {
            $this->SetStatus(104);
            $this->SetValue('SSEStatus', 'Nicht konfiguriert');

            $this->SetTimerInterval('SM_TokenRefresh', 0);
            $this->DA_StopWatchdog();
            return;
        }

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
            $this->DA_SetAvailable(false, 'Authentifizierung fehlgeschlagen');
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
                // Heartbeat â€“ connection is alive
                $this->SendDebug('SSE', 'PING received', 0);
                $this->DA_ResetWatchdog(600);
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
                    $this->DA_SetAvailable(true);
                    $this->DA_ResetWatchdog(600);
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
        $headers = [
            'Accept: application/json; charset=utf-8',
            'Content-Type: application/x-www-form-urlencoded'
        ];

        $data = $this->HttpRequest(self::TOKEN_URL, 'POST', $headers, $postData, 10);
        if ($data === null) {
            $this->SetStatus(200); // Auth failed
            $this->DA_SetAvailable(false, 'Authentifizierung fehlgeschlagen');
            return false;
        }

        if (isset($data['access_token'])) {
            $this->WriteAttributeString('AccessToken', $data['access_token']);
            if (isset($data['refresh_token'])) {
                $this->WriteAttributeString('RefreshToken', $data['refresh_token']);
            }
            $this->WriteAttributeInteger('TokenExpires', time() + ($data['expires_in'] ?? 3600));
            $this->SetStatus(102);
            return $data['access_token'];
        }

        $this->SLog('ERROR', 'Token-Anfrage fehlgeschlagen', 'Kein access_token im Response');
        $this->SetStatus(200); // Auth failed
        $this->DA_SetAvailable(false, 'Authentifizierung fehlgeschlagen');
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
        $headers = [
            'Accept: application/json; charset=utf-8',
            'Authorization: Bearer ' . $token,
            'Accept-Language: ' . $this->ReadPropertyString('Country')
        ];

        $data = $this->HttpRequest($url, 'GET', $headers, null, 10);
        return $data === null ? false : $data;
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
        $headers = [
            'Accept: */*',
            'Authorization: Bearer ' . $token,
            'Accept-Language: ' . $this->ReadPropertyString('Country')
        ];

        $result = $this->HttpRequest($url, 'PUT', $headers, $actionData, 10);
        return $result !== null;
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

    public function RequestAction(string $Ident, mixed $Value): void
    {
        match ($Ident) {
            'DA_Watchdog' => $this->DA_HandleWatchdog(),
            default => throw new Exception('Invalid Action: ' . $Ident)
        };
    }

    //==========================================================================
    // Logging
    //==========================================================================


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
            "caption": "â„¹ï¸ Die Verbindung läuft über Server-Sent Events (SSE) â€“ Statusänderungen kommen in Echtzeit, ohne Polling."
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
        },
        {
            "code": 203,
            "icon": "inactive",
            "caption": "SSE Verbindung unterbrochen"
        },
        {
            "code": 204,
            "icon": "error",
            "caption": "Watchdog: Kein Signal"
        }
    ]
}
EOT;
    }
}
