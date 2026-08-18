<?php

$files = [
    'ImperialDishwasherAI/module.php',
    'MieleDryer/module.php',
    'MieleFridge/module.php',
    'MieleHob/module.php',
    'MieleHood/module.php',
    'MieleWasher/module.php'
];

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // 1. Add require_once
    if (strpos($content, 'Trait_DeviceRegistration.php') === false) {
        $content = preg_replace('/(declare\(strict_types=1\);)/', "$1\n\nrequire_once __DIR__ . '/../libs/Trait_DeviceRegistration.php';", $content);
    }
    
    // 2. Add use trait
    if (strpos($content, 'use DeviceRegistration_Trait;') === false) {
        $content = preg_replace('/(class\s+[a-zA-Z0-9_]+\s+extends\s+IPSModuleStrict\s*\{)/', "$1\n    use DeviceRegistration_Trait;", $content);
    }
    
    // 3. Remove DR_Register from ApplyChanges if it exists
    $content = preg_replace('/^[ \t]*\$this->DR_Register\([^\)]+\);\s*$/m', '', $content);
    
    // 4. Add DR_Register to Create
    if (strpos($content, 'DR_Register') === false) {
        // Find end of Create function
        // Find "public function Create(): void {" or similar
        // We'll use a regex that matches the Create function and captures everything up to the final closing brace of that function
        // A simpler way: Find "public function ApplyChanges" and insert before it?
        // Let's just do a specific search.
        $content = preg_replace('/(public function Create\(\)[^{]*\{.*?)(\n[ \t]*\}(?=\s*public function ApplyChanges))/s', "$1\n        \$this->DR_Register('DevicesGenericSensor');$2", $content);
    }
    
    // 5. Add Destroy method
    if (strpos($content, 'public function Destroy()') === false) {
        $destroyMethod = "\n    public function Destroy(): void {\n        parent::Destroy();\n        \$this->DR_Unregister();\n    }\n";
        if (strpos($content, 'public function ApplyChanges()') !== false) {
            $content = str_replace('public function ApplyChanges()', ltrim($destroyMethod) . "\n    public function ApplyChanges()", $content);
        } else {
            $content = preg_replace('/\}\s*$/', $destroyMethod . "\n}", $content);
        }
    }
    
    file_put_contents($file, $content);
    echo "Processed $file\n";
}

