<?php
if (PHP_SAPI !== 'cli') exit('CLI only');
libxml_use_internal_errors(true);
foreach (['login.php', 'home.php', 'service-detail.php'] as $file) {
    $html = file_get_contents(__DIR__ . '/../master/welcome-page/' . $file);
    $html = preg_replace('/<\?(?:php|=)[\s\S]*?\?>/', '', $html);
    $doc = new DOMDocument();
    $doc->loadHTML($html);
    $xpath = new DOMXPath($doc);
    $stages = $xpath->query('//*[@data-registration-stage]');
    if ($stages->length !== 3) throw new RuntimeException("$file must have three screens");
    foreach (['details', 'contact', 'password'] as $stage) {
        $nodes = $xpath->query('//*[@data-registration-stage="' . $stage . '"]');
        if ($nodes->length !== 1) throw new RuntimeException("$file has duplicate $stage screens");
        $panel = $nodes->item(0);
        if ($panel->parentNode !== $stages->item(0)->parentNode) {
            throw new RuntimeException("$file has nested screens that could overlap");
        }
        $passwords = $xpath->query('.//input[@name="password"]', $panel)->length;
        $submit = $xpath->query('.//button[@type="submit"]', $panel)->length;
        if ($passwords !== ($stage === 'password' ? 1 : 0) || $submit !== ($stage === 'password' ? 1 : 0)) {
            throw new RuntimeException("$file must show password and Create Account only on the password screen");
        }
    }
    $contact = $xpath->query('//*[@data-registration-stage="contact"]')->item(0);
    if ($xpath->query('.//input[@name="email"]', $contact)->length !== 1
        || $xpath->query('.//input[@id="reg-contact-mobile"]', $contact)->length !== 1) {
        throw new RuntimeException("$file contact screen must have both contact choices");
    }
    echo "PASS $file registration screen structure\n";
}
