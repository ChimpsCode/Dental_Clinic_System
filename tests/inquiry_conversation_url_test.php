<?php
require __DIR__ . '/../includes/inquiry_module_helper.php';

function checkInquiryUrl(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$token = str_repeat('a', 64);
$_SERVER['HTTPS'] = 'on';
$_SERVER['HTTP_HOST'] = 'rfdentalclinic.dev';
foreach (['', '/Dental_Clinic_System'] as $base) {
    foreach (['/master/api/public_contact.php', '/master/shared/inquiry_actions.php', '/master/welcome-page/inquiry_reply.php'] as $script) {
        $_SERVER['SCRIPT_NAME'] = $base . $script;
        $url = inquiryConversationUrl($token);
        checkInquiryUrl($url === 'https://rfdentalclinic.dev' . $base . '/master/welcome-page/inquiry_reply.php?token=' . $token, 'Conversation URL must use an existing PHP page in the correct installation directory');
        checkInquiryUrl(inquiryPublicAssetUrl('master/api/public_inquiry_reply.php') === $base . '/master/api/public_inquiry_reply.php', 'Reply API must remain within the same installation');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        checkInquiryUrl($query['token'] === $token, 'The conversation token must survive the link unchanged');
    }
}
$_SERVER['HTTPS'] = 'off';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
checkInquiryUrl(strpos(inquiryConversationUrl($token), 'http://localhost:8080/') === 0, 'Local HTTP installations must still work');
checkInquiryUrl(is_file(__DIR__ . '/../master/welcome-page/inquiry_reply.php'), 'Email link target must exist');
checkInquiryUrl(is_file(__DIR__ . '/../master/api/public_inquiry_reply.php'), 'Reply endpoint must exist');
echo "PASS: root and subdirectory email links, unchanged tokens, reply API paths, and local HTTP hosting.\n";
