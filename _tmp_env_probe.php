<?php
// Is curl available in the WEB SAPI (Apache), vs the CLI SAPI?
echo "sapi: " . php_sapi_name() . "\n";
echo "cli curl_init: " . (function_exists('curl_init') ? 'YES' : 'NO') . "\n";
echo "cli shell_exec: " . (function_exists('shell_exec') ? 'YES' : 'NO') . "\n";
echo "ini: " . php_ini_loaded_file() . "\n";
echo "disable_functions: " . ini_get('disable_functions') . "\n";
echo "loaded ext: " . (extension_loaded('curl') ? 'curl YES' : 'curl NO') . "\n";
