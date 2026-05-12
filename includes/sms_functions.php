<?php

define('SMS_API_KEY', '073fbf9a79ffc72ac784df4fb28f832690ac6aa519e01942');
define('SMS_API_URL', 'https://api.smsmobileapi.com/sendsms/');
define('SMS_SENDER_NAME', 'RF Dental');

function sendSMS($phone, $message) {
    $phone = formatPhoneNumber($phone);
    
    $url = SMS_API_URL . '?recipients=' . urlencode($phone) . '&message=' . urlencode($message) . '&apikey=' . SMS_API_KEY;
    
    error_log("SMS URL being called: " . $url);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_errno($ch);
    curl_close($ch);
    
    error_log("SMS Response: " . $response);
    
    if ($error) {
        error_log("SMS Error (cURL): " . curl_strerror($error));
        return ['success' => false, 'error' => curl_strerror($error)];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['result']['error']) && $result['result']['error'] == 0) {
        return [
            'success' => true,
            'message' => 'SMS sent successfully',
            'sms_id' => $result['result']['id'] ?? null
        ];
    } else {
        $errorCode = $result['result']['error'] ?? 'unknown';
        $errorMsg = 'API Error code: ' . $errorCode;
        
        switch ($errorCode) {
            case '50':
                $errorMsg = 'Invalid API key or authentication failed. Please check your SMSMobileAPI dashboard for the correct API key.';
                break;
            case '51':
                $errorMsg = 'Insufficient credits. Please top up your SMS credits.';
                break;
            case '52':
                $errorMsg = 'Invalid phone number format.';
                break;
            case 'subscription_expire':
                $errorMsg = 'Your SMSMobileAPI trial has expired. Please renew or purchase credits at smsmobileapi.com.';
                break;
            default:
                $errorMsg = 'SMS API error: ' . ($result['result']['error_message'] ?? $errorCode);
        }
        
        error_log("SMS API Error: " . $errorMsg);
        return [
            'success' => false,
            'error' => $errorMsg,
            'error_code' => $errorCode,
            'response' => $result
        ];
    }
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

function sendAppointmentConfirmationSMS($phone, $patientName, $appointmentDate, $appointmentTime, $treatment) {
    $formattedDate = date('F j, Y', strtotime($appointmentDate));
    $formattedTime = date('g:i A', strtotime($appointmentTime));
    
    $message = "Thanks for booking with us! Your schedule is {$formattedDate} at {$formattedTime} (Treatment: {$treatment}). See you soon! - RF Dental Clinic";
    
    return sendSMS($phone, $message);
}

function sendAppointmentCancellationSMS($phone, $patientName, $appointmentDate, $cancelReason) {
    $formattedDate = date('F j, Y', strtotime($appointmentDate));
    
    $message = "Sorry to inform that your appointment on {$formattedDate} has been cancelled. Reason: {$cancelReason}. Please contact us if you have any questions. - RF Dental Clinic";
    
    return sendSMS($phone, $message);
}

function sendRegistrationWelcomeSMS($phone, $firstName) {
    $message = "Welcome to RF Dental Clinic, {$firstName}! Your account has been created successfully. You can now book appointments online. See you soon!";
    
    return sendSMS($phone, $message);
}