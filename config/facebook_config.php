<?php
define('FB_APP_ID', (string)(getenv('FB_APP_ID') ?: ''));
define('FB_APP_SECRET', (string)(getenv('FB_APP_SECRET') ?: ''));
define('FB_PAGE_ACCESS_TOKEN', (string)(getenv('FB_PAGE_ACCESS_TOKEN') ?: ''));
define('FB_PAGE_ID', (string)(getenv('FB_PAGE_ID') ?: ''));
define('FB_VERIFY_TOKEN', (string)(getenv('FB_VERIFY_TOKEN') ?: ''));
define('FB_GRAPH_VERSION', (string)(getenv('FB_GRAPH_VERSION') ?: 'v19.0'));
define('FB_GRAPH_URL', 'https://graph.facebook.com/' . FB_GRAPH_VERSION);
define('FB_AUTO_REPLY_MESSAGE', 'Thank you for messaging RF Dental Clinic! We have received your inquiry and will get back to you shortly.');
