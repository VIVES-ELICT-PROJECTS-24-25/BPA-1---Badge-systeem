<?php
// External Printer API Integration
// This file contains utilities for connecting to the external printer API

// Configuratie
$api_url = 'https://3dprintersmaaklabvives.be/api_test/printer_api.php';
 
// Functie om de API aan te roepen
function getPrinterData($url) {
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => [
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]
    ];
 
    $context = stream_context_create($options);
   
    try {
        $response = file_get_contents($url, false, $context);
       
        if ($response === false) {
            return [
                'success' => false,
                'message' => 'Kan geen verbinding maken met de printer API',
                'error' => error_get_last()
            ];
        }
       
        // Probeer JSON te decoderen
        $data = json_decode($response, true);
       
        // Check of de API JSON teruggeeft
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Geen JSON, probeer het als platte tekst te verwerken
            $lines = explode("\n", $response);
            $parsedData = [];
           
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
               
                // Probeer key-value paren te vinden
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $parsedData[trim($key)] = trim($value);
                } else {
                    // Als er geen key-value is, gebruik de hele lijn
                    $parsedData[] = $line;
                }
            }
           
            return [
                'success' => true,
                'is_json' => false,
                'is_parsed' => !empty($parsedData),
                'data' => empty($parsedData) ? $response : $parsedData
            ];
        }
       
        // JSON response
        return [
            'success' => true,
            'is_json' => true,
            'data' => $data
        ];
       
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Fout bij het ophalen van printer data',
            'error' => $e->getMessage()
        ];
    }
}
 
// Helper functie om recursief te bepalen of een array een associatieve array is
function isAssoc($arr) {
    if (!is_array($arr)) return false;
    if (empty($arr)) return false;
    return array_keys($arr) !== range(0, count($arr) - 1);
}
 
// Helper functie om de waarde op te maken voor weergave
function formatValue($value) {
    if (is_array($value)) {
        if (empty($value)) return '(lege array)';
        return '<pre>' . htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT)) . '</pre>';
    } elseif (is_bool($value)) {
        return $value ? 'true' : 'false';
    } elseif (is_null($value)) {
        return 'null';
    } else {
        return htmlspecialchars((string)$value);
    }
}
?>