<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/api_keys.php';

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get the user's message
$data = json_decode(file_get_contents('php://input'), true);
$userMessage = $data['message'] ?? '';

if (empty($userMessage)) {
    echo json_encode(['error' => 'Message is empty']);
    exit;
}

if (!defined('GEMINI_API_KEY') || trim(GEMINI_API_KEY) === '') {
    echo json_encode(['response' => "I'm ready to help, but the Gemini API key hasn't been set up yet. Please ask the administrator to paste the API key into config/api_keys.php!"]);
    exit;
}

$clinicPhone = '0967 187 8603'; // Default fallback

try {
    // 1. Gather Clinic Context from Database
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $clinicSettings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $clinicName = $clinicSettings['clinic_name'] ?? 'RF Dental Clinic';
    $clinicPhone = $clinicSettings['clinic_phone'] ?? $clinicPhone;
    $clinicEmail = $clinicSettings['clinic_email'] ?? 'rfdc2023@gmail.com';
    $clinicAddress = $clinicSettings['clinic_address'] ?? 'Celeste Bldg. MaxSuniel St. Brgy. Carmen, Cagayan de Oro';

    $servicesStmt = $pdo->query("SELECT name, price, duration_minutes, description FROM services WHERE is_active = 1 ORDER BY name");
    $services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

    $servicesList = "";
    foreach ($services as $svc) {
        $servicesList .= "- {$svc['name']}: ₱" . number_format($svc['price'], 2) . " (approx. {$svc['duration_minutes']} min). {$svc['description']}\n";
    }

    $systemPrompt = "You are 'RF-ChatBot', the friendly, professional AI receptionist for {$clinicName}. Your goal is to assist patients with clinic information, services, pricing, and scheduling.
CLINIC INFO: Name: {$clinicName}, Phone: {$clinicPhone}, Email: {$clinicEmail}, Address: {$clinicAddress}.
SERVICES: {$servicesList}
RULES: 1. Only answer dental/clinic related questions. 2. If off-topic, politely decline. 3. NEVER give medical diagnoses; advise booking a consultation for pain/symptoms. 4. Guide users to the contact form or call {$clinicPhone} to book.
RESPONSE FORMAT RULES:
- If the user is asking about services, treatments, procedures, prices, costs, or fees (including typos, misspellings, or variations like 'pricesss', 'servicess', 'magkano'), start your response with exactly '[TYPE:SERVICES]' then explain briefly.
- If the user wants to book, schedule, or reserve an appointment (including typos or variations like 'bok', 'apointment', 'reserb'), start your response with exactly '[TYPE:BOOKING]' then explain briefly.
- Otherwise, respond normally without any prefix.";

    // 2. Prepare API Request
    $postData = [
        "contents" => [["parts" => [["text" => $systemPrompt . "\n\nUser: " . $userMessage]]]]
    ];
    $jsonPostData = json_encode($postData);

    // 3. Try Multiple Models (Fallback Mechanism)
    // We try Flash 1.5, then Pro 1.5, then the older Pro 1.0 models
    $modelsToTry = ['gemini-1.5-flash', 'gemini-1.5-pro', 'gemini-pro', 'gemini-1.0-pro'];
    $lastResponse = '';
    $success = false;
    $aiText = '';
    // First, query the ModelService ListModels for each API version to know which models are available
    $apiVersions = ['v1beta', 'v1'];
    $availableModelsByVersion = [];
    foreach ($apiVersions as $ver) {
        $listUrl = "https://generativelanguage.googleapis.com/{$ver}/models?key=" . trim(GEMINI_API_KEY);
        $modelsResp = null;
        if (function_exists('curl_version')) {
            $ch = curl_init($listUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Clinic-Inquiry-Bot/1.1']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $modelsResp = curl_exec($ch);
            curl_close($ch);
        } else {
            $modelsResp = @file_get_contents($listUrl);
        }

        $availableModelsByVersion[$ver] = [];
        if ($modelsResp) {
            $md = json_decode($modelsResp, true);
            if (is_array($md) && isset($md['models']) && is_array($md['models'])) {
                foreach ($md['models'] as $m) {
                    if (isset($m['name'])) {
                        // name may be like 'models/gemini-1.5-pro'
                        $availableModelsByVersion[$ver][] = $m['name'];
                        // also add short name variant
                        $short = preg_replace('#^models/#', '', $m['name']);
                        $availableModelsByVersion[$ver][] = $short;
                    }
                }
            }
        }
    }

    // Build a prioritized list of models to attempt per version based on available models
    $candidatesByVersion = [];
    foreach ($apiVersions as $ver) {
        $available = $availableModelsByVersion[$ver] ?? [];
        $candidatesByVersion[$ver] = [];
        if (!empty($available)) {
            // Prefer models in our preferred list if present
            foreach ($modelsToTry as $m) {
                if (in_array($m, $available, true) || in_array('models/'.$m, $available, true)) {
                    $candidatesByVersion[$ver][] = $m;
                }
            }
            // If none of our preferred models are present, pick the first available model as a fallback
            if (empty($candidatesByVersion[$ver])) {
                // pick the first non-empty unique model name (prefer short form)
                $first = null;
                foreach ($available as $av) {
                    $short = preg_replace('#^models/#', '', $av);
                    if ($short) { $first = $short; break; }
                }
                if ($first) {
                    $candidatesByVersion[$ver][] = $first;
                }
            }
        }
    }

    // If no models available across versions, provide a helpful error
    $anyAvailable = false;
    foreach ($candidatesByVersion as $v) { if (!empty($v)) { $anyAvailable = true; break; } }
    if (!$anyAvailable) {
        throw new Exception("No models available for this API key. Please verify the Gemini API key, project permissions, and that the Model Service ListModels returns available models.");
    }

    // Iterate only over models that the API reported as available per version
    foreach ($apiVersions as $ver) {
        $candidates = $candidatesByVersion[$ver] ?? [];
        if (empty($candidates)) continue;
        foreach ($candidates as $modelName) {
            $apiUrl = "https://generativelanguage.googleapis.com/{$ver}/models/{$modelName}:generateContent?key=" . trim(GEMINI_API_KEY);

            if (function_exists('curl_version')) {
                $ch = curl_init($apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPostData);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'User-Agent: Clinic-Inquiry-Bot/1.1'
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

                $response = curl_exec($ch);
                $curlErr = curl_error($ch);
                $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($response === false || $response === null) {
                    $lastResponse = "Ver: {$ver}, Model: {$modelName}, HTTP: {$httpStatus}. cURL error: " . ($curlErr ?: 'empty response');
                    continue;
                }

                $trimmed = ltrim($response);
                if (strlen($trimmed) > 0 && $trimmed[0] === '<') {
                    $snippet = substr($trimmed, 0, 512);
                    $lastResponse = "Ver: {$ver}, Model: {$modelName}, HTTP: {$httpStatus}. Non-JSON response (likely HTML): " . $snippet;
                    continue;
                }

                $responseData = json_decode($response, true);
                if ($responseData === null && json_last_error() !== JSON_ERROR_NONE) {
                    $lastResponse = "Ver: {$ver}, Model: {$modelName}, HTTP: {$httpStatus}. Invalid JSON response: " . json_last_error_msg();
                    continue;
                }
            } else {
                $options = [
                    'http' => [
                        'header'  => "Content-type: application/json\r\nUser-Agent: Clinic-Inquiry-Bot/1.1\r\n",
                        'method'  => 'POST',
                        'content' => $jsonPostData,
                        'ignore_errors' => true
                    ]
                ];

                $context  = stream_context_create($options);
                $response = @file_get_contents($apiUrl, false, $context);
                if ($response === false || $response === null) {
                    $httpStatus = 0;
                    if (isset($http_response_header) && is_array($http_response_header) && preg_match('/HTTP\/\d+\.\d+\s+(\d+)/', $http_response_header[0], $m)) {
                        $httpStatus = (int)$m[1];
                    }
                    $lastResponse = "Ver: {$ver}, Model: {$modelName}, HTTP: {$httpStatus}. file_get_contents failed";
                    continue;
                }

                $httpStatus = 0;
                if (isset($http_response_header) && is_array($http_response_header)) {
                    if (preg_match('/HTTP\/\d+\.\d+\s+(\d+)/', $http_response_header[0], $matches)) {
                        $httpStatus = (int)$matches[1];
                    }
                }

                $trimmed = ltrim($response);
                if (strlen($trimmed) > 0 && $trimmed[0] === '<') {
                    $snippet = substr($trimmed, 0, 512);
                    $lastResponse = "Ver: {$ver}, Model: {$modelName}, HTTP: {$httpStatus}. Non-JSON response (likely HTML): " . $snippet;
                    continue;
                }

                $responseData = json_decode($response, true);
                if ($responseData === null && json_last_error() !== JSON_ERROR_NONE) {
                    $lastResponse = "Ver: {$ver}, Model: {$modelName}, HTTP: {$httpStatus}. Invalid JSON response: " . json_last_error_msg();
                    continue;
                }
            }

            if ($httpStatus === 200 && isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                $aiText = $responseData['candidates'][0]['content']['parts'][0]['text'];
                if (!empty($aiText)) {
                    $success = true;
                    break;
                }
            }

            $errorMsg = 'Unknown error';
            if (is_array($responseData) && isset($responseData['error'])) {
                $err = $responseData['error'];
                if (is_array($err)) {
                    $errorMsg = $err['message'] ?? ($err['status'] ?? json_encode($err));
                } else {
                    $errorMsg = (string)$err;
                }
            }
            $lastResponse = "Ver: {$ver}, Model: {$modelName}, Status: {$httpStatus}. Error: " . $errorMsg;
        }
        if ($success) break;
    }

    if (!$success) {
        throw new Exception("All Gemini models failed. Last error: " . $lastResponse);
    }

    echo json_encode(['response' => trim($aiText)]);

} catch (Exception $e) {
    // For debugging, we'll show the actual error message
    $debugError = $e->getMessage();
    echo json_encode([
        'error' => $debugError, 
        'response' => "I'm sorry, I'm experiencing a technical difficulty: " . $debugError
    ]);
}
