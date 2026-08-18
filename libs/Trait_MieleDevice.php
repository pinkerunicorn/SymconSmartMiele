<?php

declare(strict_types=1);

trait MieleDevice_Trait
{
    protected function SetValueIfChanged(string $ident, mixed $value): bool
    {
        if ($this->GetValue($ident) !== $value) {
            $this->SetValue($ident, $value);
            return true;
        }
        return false;
    }

    private function TranslateMieleText(string $text): string
    {
        $map = [
            'Cottons' => 'Baumwolle', 'Cottons Eco' => 'Baumwolle Eco', 'Minimum iron' => 'Pflegeleicht',
            'Delicates' => 'Feinwäsche', 'Woollens' => 'Wolle', 'Silks' => 'Seide',
            'Shirts' => 'Oberhemden', 'Denim' => 'Jeans', 'Dark garments' => 'Dunkles/Jeans',
            'Outerwear' => 'Outdoor', 'Proofing' => 'Imprägnieren', 'Sportswear' => 'Sportwäsche',
            'Trainers' => 'Sportschuhe', 'Drain/Spin' => 'Pumpen/Schleudern', 'Separate Rinse' => 'Nur Spülen',
            'Mixed items' => 'Automatic plus', 'Express 20' => 'Express 20',
            'Extra dry' => 'Extratrocken', 'Normal plus' => 'Schranktrocken+', 'Normal' => 'Schranktrocken',
            'Slightly dry' => 'Leicht trocken', 'Hand iron' => 'Bügelfeucht', 'Machine iron' => 'Mangelfeucht',
            'Smoothing' => 'Glätten',
            'Off' => 'Aus', 'Idle' => 'Bereit', 'Programmed' => 'Programmiert', 'Waiting to start' => 'Wartet auf Start',
            'Running' => 'Läuft', 'In use' => 'In Betrieb', 'Pause' => 'Pause', 'Program ended' => 'Programm beendet',
            'Finished' => 'Fertig', 'Failure' => 'Fehler', 'Program cancelled' => 'Abgebrochen',
            'Pre-wash' => 'Vorwäsche', 'Main wash' => 'Hauptwäsche', 'Rinse' => 'Spülen',
            'Rinse & Hold' => 'Spülstopp', 'Spin' => 'Schleudern', 'Drying' => 'Trocknen',
            'Cooling down' => 'Abkühlen', 'Anti-crease' => 'Knitterschutz'
        ];
        return $map[$text] ?? $text;
    }

    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        if (!is_array($data)) return "";

        if (($data['DataID'] ?? '') == '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}') {
            $deviceId = $this->ReadPropertyString('DeviceID');
            if (empty($deviceId)) return "";

            $type = $data['Type'] ?? 'DeviceUpdate';

            if (($type === 'DeviceUpdate' || !isset($data['Type'])) && isset($data['Devices'][$deviceId])) {
                $this->Miele_ProcessDeviceData($data['Devices'][$deviceId]);
                
                if (method_exists($this, 'DA_SetAvailable')) {
                    $this->DA_SetAvailable(true);
                }
                if (method_exists($this, 'DA_ResetWatchdog')) {
                    $this->DA_ResetWatchdog(600);
                }
                
                // Hook fr Custom (z.B. Fetching TwinDos)
                if (method_exists($this, 'Miele_OnDeviceUpdate')) {
                    $this->Miele_OnDeviceUpdate($deviceId);
                }
            }
            if ($type === 'ActionsUpdate' && isset($data['Actions'][$deviceId])) {
                if (method_exists($this, 'SetBuffer')) {
                    $this->SetBuffer('DeviceActions', json_encode($data['Actions'][$deviceId]));
                    $this->SetBuffer('ActionsData', json_encode($data['Actions'][$deviceId]));
                }
                if (method_exists($this, 'Miele_OnActionsUpdate')) {
                    $this->Miele_OnActionsUpdate($data['Actions'][$deviceId]);
                }
            }
        }
        return "";
    }

    public function UpdateDevice(): void
    {
        $deviceId = $this->ReadPropertyString('DeviceID');
        if (empty($deviceId)) {
            echo "Fehler: Bitte zuerst eine Device ID eintragen.\n";
            return;
        }

        $payload = [
            'DataID' => '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}',
            'Command' => 'ApiGet',
            'Endpoint' => '/v1/devices/' . urlencode($deviceId) . '/state'
        ];
        
        $result = $this->SendDataToParent(json_encode($payload));
        $state = json_decode($result, true);

        if ($state && is_array($state) && !isset($state['message'])) {
            $this->Miele_ProcessDeviceData(['state' => $state]);
            
            if (method_exists($this, 'DA_SetAvailable')) {
                $this->DA_SetAvailable(true);
            }
            if (method_exists($this, 'DA_ResetWatchdog')) {
                $this->DA_ResetWatchdog(600);
            }
            
            if (method_exists($this, 'Miele_OnDeviceUpdate')) {
                $this->Miele_OnDeviceUpdate($deviceId);
            }
            echo "Gert erfolgreich aktualisiert!\n";
        } else {
            if (method_exists($this, 'DA_SetAvailable')) {
                $this->DA_SetAvailable(false, 'API-Fehler beim manuellen Update');
            }
            if (isset($state['message'])) {
                if (method_exists($this, 'SLog')) {
                    $this->SLog('ERROR', 'Miele Update-Fehler', $state['message'] ?? 'Unbekannter Fehler');
                }
            } else {
                echo "Fehler beim Update: Konnte keine Daten abrufen. Bitte API-Verbindung und Device ID prfen.\n";
            }
        }
    }

    protected function Miele_SendAction(array $actionData): bool
    {
        $deviceId = $this->ReadPropertyString('DeviceID');
        if (empty($deviceId)) {
            if (method_exists($this, 'SLogInfo')) {
                $this->SLogInfo("Fehler: Device ID fehlt.");
            }
            return false;
        }

        $payload = [
            'DataID' => '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}',
            'Command' => 'ExecuteAction',
            'DeviceID' => $deviceId,
            'ActionData' => $actionData
        ];
        $res = $this->SendDataToParent(json_encode($payload));
        return (bool)json_decode($res, true);
    }

    protected function Miele_ProcessDeviceData(array $deviceData): void
    {
        if (isset($deviceData['state'])) {
            $state = $deviceData['state'];

            // === TEXT & STATUS ===
            if (isset($state['status']['value_localized']) && @$this->GetIDForIdent('StatusText') !== false) {
                $newStatus = $this->TranslateMieleText((string)$state['status']['value_localized']);
                if (@$this->GetValue('StatusText') !== $newStatus) {
                    if (method_exists($this, 'SLogInfo')) {
                        $this->SLogInfo("Status geändert: " . $newStatus);
                    }
                }
                $this->SetValueIfChanged('StatusText', $newStatus);
            }

            if (isset($state['status']['value_raw'])) {
                $statusRaw = (int)$state['status']['value_raw'];
                // PowerOn: off (1) = false, anything else = true
                if (@$this->GetIDForIdent('PowerOn') !== false) {
                    $this->SetValueIfChanged('PowerOn', $statusRaw !== 1);
                }
            }

            // PowerSupply
            if (isset($state['powerSupply']['value_raw']) && @$this->GetIDForIdent('PowerSupply') !== false) {
                $this->SetValueIfChanged('PowerSupply', (int)$state['powerSupply']['value_raw']);
            }
            
            // Signals
            if (isset($state['signalInfo']) && @$this->GetIDForIdent('SignalInfo') !== false) {
                $this->SetValueIfChanged('SignalInfo', (bool)$state['signalInfo']);
            }
            if (isset($state['signalFailure']) && @$this->GetIDForIdent('SignalFailure') !== false) {
                $this->SetValueIfChanged('SignalFailure', (bool)$state['signalFailure']);
            }
            if (isset($state['signalDoor'])) {
                if (@$this->GetIDForIdent('Door') !== false) {
                    $this->SetValueIfChanged('Door', (bool)$state['signalDoor']);
                }
                if (@$this->GetIDForIdent('DoorOpen') !== false) {
                    $this->SetValueIfChanged('DoorOpen', (bool)$state['signalDoor']);
                }
            }
            
            // Program info
            if (isset($state['ProgramID']['value_localized']) && @$this->GetIDForIdent('ProgramName') !== false) {
                $this->SetValueIfChanged('ProgramName', $this->TranslateMieleText((string)$state['ProgramID']['value_localized']));
            }
            if (isset($state['programPhase']['value_localized']) && @$this->GetIDForIdent('ProgramPhaseText') !== false) {
                $this->SetValueIfChanged('ProgramPhaseText', $this->TranslateMieleText((string)$state['programPhase']['value_localized']));
            }

            // Target Temperature
            if (isset($state['targetTemperature'][0]['value_raw'])) {
                $t = $state['targetTemperature'][0]['value_raw'];
                if (@$this->GetIDForIdent('TargetTemp1') !== false) { // Fridge
                    $this->SetValueIfChanged('TargetTemp1', (int)round($t / 100.0));
                }
                if (@$this->GetIDForIdent('Temperature') !== false && $t > -100) { // Washer
                    if ($t >= 1000) {
                        $this->SetValueIfChanged('Temperature', (int)($t / 100));
                    } else {
                        $this->SetValueIfChanged('Temperature', (int)$t);
                    }
                }
            }
            
            // Current Temperature
            if (isset($state['temperature'][0]['value_raw'])) {
                if (@$this->GetIDForIdent('Temp1') !== false) {
                    $valTemp = (int)round($state['temperature'][0]['value_raw'] / 100.0);
                    $this->SetValueIfChanged('Temp1', $valTemp);
                }
            }

            // Spinning Speed
            if (isset($state['spinningSpeed']['value_raw']) && @$this->GetIDForIdent('SpinSpeed') !== false) {
                $s = $state['spinningSpeed']['value_raw'];
                if ($s > -1) $this->SetValueIfChanged('SpinSpeed', (int)$s);
            }
            // Dryness Level
            if (isset($state['dryingStep']) && @$this->GetIDForIdent('DrynessLevel') !== false) {
                $valLoc = (string)($state['dryingStep']['value_localized'] ?? '');
                if (trim($valLoc) !== '') {
                    $this->SetValueIfChanged('DrynessLevel', $this->TranslateMieleText($valLoc));
                } else if (isset($state['dryingStep']['value_raw']) && (int)$state['dryingStep']['value_raw'] > 0) {
                    $this->SetValueIfChanged('DrynessLevel', 'Stufe ' . (int)$state['dryingStep']['value_raw']);
                } else {
                    $this->SetValueIfChanged('DrynessLevel', '-');
                }
            }

            // Eco Feedback
            if (isset($state['ecoFeedback']['currentWaterConsumption']['value']) && @$this->GetIDForIdent('CurrentWaterConsumption') !== false) {
                $water = (float)$state['ecoFeedback']['currentWaterConsumption']['value'];
                $this->SetValueIfChanged('CurrentWaterConsumption', $water);
                if ($water > 0 && @$this->GetIDForIdent('LastWaterConsumption') !== false) {
                    if (method_exists($this, 'WriteAttributeFloat')) $this->WriteAttributeFloat('LastWater', $water);
                    $this->SetValueIfChanged('LastWaterConsumption', $water);
                } else if (@$this->GetIDForIdent('LastWaterConsumption') !== false) {
                    if (method_exists($this, 'ReadAttributeFloat')) $this->SetValueIfChanged('LastWaterConsumption', $this->ReadAttributeFloat('LastWater'));
                }
            }
            if (isset($state['ecoFeedback']['currentEnergyConsumption']['value']) && @$this->GetIDForIdent('CurrentEnergyConsumption') !== false) {
                $energy = (float)$state['ecoFeedback']['currentEnergyConsumption']['value'];
                $this->SetValueIfChanged('CurrentEnergyConsumption', $energy);
                if ($energy > 0 && @$this->GetIDForIdent('LastEnergyConsumption') !== false) {
                    if (method_exists($this, 'WriteAttributeFloat')) $this->WriteAttributeFloat('LastEnergy', $energy);
                    $this->SetValueIfChanged('LastEnergyConsumption', $energy);
                } else if (@$this->GetIDForIdent('LastEnergyConsumption') !== false) {
                    if (method_exists($this, 'ReadAttributeFloat')) $this->SetValueIfChanged('LastEnergyConsumption', $this->ReadAttributeFloat('LastEnergy'));
                }
            }

            // --- Time & Progress Calculation (Common for Washer/Dryer) ---
            if (@$this->GetIDForIdent('RemainingTime') !== false || @$this->GetIDForIdent('ElapsedTime') !== false) {
                $statusRaw = $state['status']['value_raw'] ?? 0;
                
                $remMinutes = @$this->GetValue('RemainingTime') ?: 0;
                if (isset($state['remainingTime']) && is_array($state['remainingTime']) && count($state['remainingTime']) == 2) {
                    $remMinutes = ($state['remainingTime'][0] * 60) + $state['remainingTime'][1];
                } else if (isset($state['remainingTime']) && is_array($state['remainingTime']) && count($state['remainingTime']) == 0) {
                    if ($statusRaw != 5 && $statusRaw != 7) $remMinutes = 0;
                }

                $elapsedMinutes = @$this->GetValue('ElapsedTime') ?: 0;
                if (isset($state['elapsedTime']) && is_array($state['elapsedTime']) && count($state['elapsedTime']) == 2) {
                    $elapsedMinutes = ($state['elapsedTime'][0] * 60) + $state['elapsedTime'][1];
                } else if (isset($state['elapsedTime']) && is_array($state['elapsedTime']) && count($state['elapsedTime']) == 0) {
                    if ($statusRaw != 5 && $statusRaw != 7) $elapsedMinutes = 0;
                }

                if ($statusRaw == 7) { // Finished
                    $remMinutes = 0;
                    $progress = 100;
                } else if ($statusRaw == 5) { // In Use
                    $now = (int)(floor(time() / 60) * 60); // Strip seconds
                    $oldAnchor = 0;
                    if (method_exists($this, 'ReadAttributeInteger')) {
                        $oldAnchor = @$this->ReadAttributeInteger('AnchorStartTime') ?: 0;
                    }
                    
                    $machineElapsed = 0;
                    if (isset($state['elapsedTime']) && is_array($state['elapsedTime']) && count($state['elapsedTime']) == 2) {
                        $machineElapsed = ($state['elapsedTime'][0] * 60) + $state['elapsedTime'][1];
                    }
                    
                    if ($machineElapsed > 0) {
                        $elapsedMinutes = $machineElapsed;
                        $expectedStart = $now - ($elapsedMinutes * 60);
                        // Jitter protection: keep anchored StartTime if it's close
                        if ($oldAnchor > 0 && abs($expectedStart - $oldAnchor) < 300) {
                            $anchor = $oldAnchor;
                        } else {
                            $anchor = $expectedStart;
                        }
                    } else {
                        // Miele Washer/Dryer send often no elapsed time. We freeze the start time.
                        if ($oldAnchor > 0 && $oldAnchor <= time()) {
                            $anchor = $oldAnchor;
                        } else {
                            $anchor = $now;
                        }
                        $elapsedMinutes = (int)round((time() - $anchor) / 60);
                    }
                    if (method_exists($this, 'WriteAttributeInteger')) {
                        @$this->WriteAttributeInteger('AnchorStartTime', $anchor);
                    }
                    
                    $total = $elapsedMinutes + $remMinutes;
                    if ($remMinutes > 0) {
                        $progress = (int)round(($elapsedMinutes / $total) * 100);
                    } else if ($elapsedMinutes > 30) {
                        $progress = 100;
                    } else {
                        $progress = 0;
                    }
                } else if ($statusRaw == 4) { // Waiting to start
                    $progress = 0;
                    $elapsedMinutes = 0;
                    // Keep remMinutes from API if provided
                } else { // Off, Idle
                    $progress = 0;
                    $elapsedMinutes = 0;
                    $remMinutes = 0;
                    if (method_exists($this, 'WriteAttributeInteger')) {
                        @$this->WriteAttributeInteger('AnchorStartTime', 0);
                    }
                }

                if (@$this->GetIDForIdent('ElapsedTime') !== false) $this->SetValueIfChanged('ElapsedTime', (int)$elapsedMinutes);
                if (@$this->GetIDForIdent('RemainingTime') !== false) $this->SetValueIfChanged('RemainingTime', (int)$remMinutes);
                if (@$this->GetIDForIdent('RemainingTimeSeconds') !== false) $this->SetValueIfChanged('RemainingTimeSeconds', (int)($remMinutes * 60));
                if (@$this->GetIDForIdent('ProgressPct') !== false) $this->SetValueIfChanged('ProgressPct', (int)$progress);
                
                // StartTime String
                if (@$this->GetIDForIdent('StartTime') !== false) {
                    if ($statusRaw == 5 || $statusRaw == 7) {
                        // Running or Finished -> actual start time
                        $anchor = @$this->ReadAttributeInteger('AnchorStartTime') ?: 0;
                        if ($anchor > 0) {
                            $this->SetValueIfChanged('StartTime', date('H:i', $anchor));
                        } else {
                            $this->SetValueIfChanged('StartTime', '-');
                        }
                    } else if ($statusRaw == 4 && isset($state['startTime']) && is_array($state['startTime']) && count($state['startTime']) == 2) {
                        // Waiting to start -> Delay Start from API
                        $delayMinutes = ($state['startTime'][0] * 60) + $state['startTime'][1];
                        if ($delayMinutes > 0) {
                            $this->SetValueIfChanged('StartTime', date('H:i', time() + ($delayMinutes * 60)));
                        } else {
                            $this->SetValueIfChanged('StartTime', '-');
                        }
                    } else {
                        $this->SetValueIfChanged('StartTime', '-');
                    }
                }
                
                // FinishTime String
                if (@$this->GetIDForIdent('FinishTime') !== false) {
                    if (($statusRaw == 5 || $statusRaw == 4) && $remMinutes > 0) { // Running or Waiting
                        $finishTime = time() + ($remMinutes * 60);
                        $this->SetValueIfChanged('FinishTime', date('H:i', $finishTime));
                    } else {
                        $this->SetValueIfChanged('FinishTime', '-');
                    }
                }
            }

            // Custom specific fields
            if (method_exists($this, 'Miele_ProcessCustomDeviceData')) {
                $this->Miele_ProcessCustomDeviceData($state);
            }
        }
    }
}
