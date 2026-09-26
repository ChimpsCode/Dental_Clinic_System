<?php

$smsCredentialsFile = __DIR__ . '/../config/sms_credentials.php';
if (is_file($smsCredentialsFile)) {
    require_once $smsCredentialsFile;
} else {
    // SMS is optional. Keep appointment and registration workflows working
    // when local credentials have not been configured.
    if (!defined('TEXTBEE_API_KEY')) {
        define('TEXTBEE_API_KEY', '');
    }
    if (!defined('TEXTBEE_DEVICE_ID')) {
        define('TEXTBEE_DEVICE_ID', '');
    }
    if (!defined('SMS_TEST_RECIPIENTS')) {
        define('SMS_TEST_RECIPIENTS', []);
    }
}

function sendSMS($phone, $message, bool $nonBlocking = false) {
    $phone = formatPhoneNumber($phone);
    return sendViaTextBee($phone, $message, $nonBlocking);
}

function sendViaTextBee($phone, $message, bool $nonBlocking = false) {
    if (!defined('TEXTBEE_API_KEY') || TEXTBEE_API_KEY === '' || TEXTBEE_API_KEY === 'YOUR_TEXBEE_API_KEY') {
        error_log("TextBee SMS skipped: TEXTBEE_API_KEY not configured");
        return ['success' => false, 'error' => 'TextBee API key not configured'];
    }
    if (!defined('TEXTBEE_DEVICE_ID') || TEXTBEE_DEVICE_ID === '' || TEXTBEE_DEVICE_ID === 'YOUR_TEXBEE_DEVICE_ID') {
        error_log("TextBee SMS skipped: TEXTBEE_DEVICE_ID not configured");
        return ['success' => false, 'error' => 'TextBee device ID not configured'];
    }

    $recipient = $phone;
    if (substr($recipient, 0, 1) !== '+') {
        $recipient = '+' . $recipient;
    }

    $body = json_encode([
        'recipients' => [$recipient],
        'message' => $message,
    ]);

    $url = 'https://api.textbee.dev/api/v1/gateway/devices/' . rawurlencode(TEXTBEE_DEVICE_ID) . '/send-sms';

    if ($nonBlocking) {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'x-api-key: ' . TEXTBEE_API_KEY,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            curl_exec($ch);
            $err = curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($err) {
                error_log("TextBee SMS (background) transport error: " . $err);
                return ['success' => false, 'error' => $err];
            }
            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'message' => 'SMS sent successfully', 'provider_status' => 'Queued'];
            }
            error_log("TextBee SMS (background) API error: HTTP $httpCode");
            return ['success' => false, 'error' => "TextBee error: HTTP $httpCode"];
        }

        $escapedUrl = escapeshellarg($url);
        $escapedHeaders = escapeshellarg("Content-Type: application/json\r\nx-api-key: " . TEXTBEE_API_KEY);
        $escapedBody = escapeshellarg($body);
        @exec("curl -s -X POST {$escapedUrl} -H {$escapedHeaders} -d {$escapedBody} --connect-timeout 3 --max-time 5 > /dev/null 2>&1 &");
        return ['success' => true, 'message' => 'SMS queued', 'provider_status' => 'Queued'];
    }

    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n" .
                    "x-api-key: " . TEXTBEE_API_KEY . "\r\n",
        'content' => $body,
        'timeout' => 30,
        'ignore_errors' => true,
    ]]);

    $response = @file_get_contents($url, false, $context);

    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
        $status = (int)$m[1];
    }

    if ($response === false) {
        $err = error_get_last();
        $msg = $err['message'] ?? 'Network error while contacting TextBee';
        error_log("TextBee SMS transport error: " . $msg);
        return ['success' => false, 'error' => $msg];
    }

    $data = json_decode($response, true);

    if ($status >= 200 && $status < 300 && is_array($data) && isset($data['data'])) {
        return [
            'success' => true,
            'message' => 'SMS sent successfully',
            'sms_id' => $data['data']['smsBatchId'] ?? null,
            'provider_status' => 'Queued'
        ];
    }

    $desc = $data['error'] ?? $data['message'] ?? ('HTTP ' . $status);
    error_log("TextBee SMS API error (HTTP $status): " . $desc);
    return ['success' => false, 'error' => 'TextBee error: ' . $desc];
}

function testTextBeeConnection() {
    if (!defined('TEXTBEE_API_KEY') || TEXTBEE_API_KEY === '' || TEXTBEE_API_KEY === 'YOUR_TEXBEE_API_KEY') {
        return ['success' => false, 'error' => 'TextBee API key not configured'];
    }

    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => "Accept: application/json\r\n" .
                    "x-api-key: " . TEXTBEE_API_KEY . "\r\n",
        'timeout' => 30,
        'ignore_errors' => true,
    ]]);

    $response = @file_get_contents('https://api.textbee.dev/api/v1/gateway/devices', false, $context);

    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
        $status = (int)$m[1];
    }

    if ($response === false) {
        $err = error_get_last();
        $msg = $err['message'] ?? 'Network error while contacting TextBee';
        error_log("TextBee connection test transport error: " . $msg);
        return ['success' => false, 'error' => $msg];
    }

    $data = json_decode($response, true);

    if ($status >= 200 && $status < 300 && is_array($data)) {
        return ['success' => true, 'status' => $status, 'response' => $response];
    }

    $desc = $data['error'] ?? $data['message'] ?? ('HTTP ' . $status);
    error_log("TextBee connection test failed (HTTP $status): " . $desc);
    return ['success' => false, 'status' => $status, 'error' => $desc];
}

function formatPhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (substr($phone, 0, 2) == '63') {
        return $phone;
    }
    
    if (substr($phone, 0, 1) == '9') {
        return '63' . $phone;
    }
    
    if (substr($phone, 0, 2) == '09') {
        return '63' . substr($phone, 1);
    }
    
    return $phone;
}

function sendAppointmentConfirmationSMS($phone, $patientName, $appointmentDate, $appointmentTime, $treatment, $isNewPatient = false, $nonBlocking = false) {
    $formattedDate = date('F j, Y', strtotime($appointmentDate));
    $formattedTime = date('g:i A', strtotime($appointmentTime));

    if ($isNewPatient) {
        $message = "Hello {$patientName}! Your appointment has been confirmed for {$formattedDate} at {$formattedTime} (Treatment: {$treatment}). See you soon! - RF Dental Clinic";
    } else {
        $message = "Thanks for booking with us! Your appointment is confirmed for {$formattedDate} at {$formattedTime} (Treatment: {$treatment}). See you soon! - RF Dental Clinic";
    }

    return sendSMS($phone, $message, $nonBlocking);
}

function sendAppointmentCancellationSMS($phone, $patientName, $appointmentDate, $cancelReason, $nonBlocking = false) {
    $formattedDate = date('F j, Y', strtotime($appointmentDate));
    
    $message = "Sorry to inform that your appointment on {$formattedDate} has been cancelled. Reason: {$cancelReason}. Please contact us if you have any questions. - RF Dental Clinic";
    
    return sendSMS($phone, $message, $nonBlocking);
}

function sendRegistrationWelcomeSMS($phone, $firstName) {
    $message = "Welcome to RF Dental Clinic, {$firstName}! Your account has been created successfully. You can now book appointments online. See you soon!";
    
    return sendSMS($phone, $message);
}

function sendAppointmentReminderSMS($phone, $patientName, $appointmentDate, $appointmentTime, $treatment, int $hoursBefore, bool $nonBlocking = false) {
    $formattedDate = date('F j, Y', strtotime($appointmentDate));
    $formattedTime = date('g:i A', strtotime($appointmentTime));
    $treatment = trim((string)$treatment) !== '' ? $treatment : 'General Checkup';

    if ($hoursBefore === 2) {
        $message = "Reminder: Hi {$patientName}! Your appointment at RF Dental Clinic is today at {$formattedTime} ({$treatment}). See you soon!";
    } else {
        $message = "Reminder: Hi {$patientName}! You have an appointment at RF Dental Clinic on {$formattedDate} at {$formattedTime} ({$treatment}). See you soon!";
    }

    return sendSMS($phone, $message, $nonBlocking);
}
