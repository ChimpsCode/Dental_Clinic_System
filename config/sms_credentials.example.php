<?php

// SMS credentials configuration — example template.
// Copy this file to config/sms_credentials.php (which is gitignored) and fill in your real values.

// TextBee.dev (SMS provider) — https://textbee.dev
// Sends SMS through the SIM of your Android phone. Register at app.textbee.dev,
// install the Android app, then copy your API key and Device ID from the dashboard.
define('TEXTBEE_API_KEY', 'YOUR_TEXBEE_API_KEY');      // TODO: paste your TextBee API key
define('TEXTBEE_DEVICE_ID', 'YOUR_TEXBEE_DEVICE_ID');   // TODO: paste your TextBee device ID

// Test recipients — numbers that master/shared/sms_test.php sends test messages to.
// Use international format without the leading +, e.g. '639171234567'. Add as many as you like.
define('SMS_TEST_RECIPIENTS', [
    '639924465990',
]);
