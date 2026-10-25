<?php
// Copy to config/config.php only if you are NOT using the installer.
// The installer (install/ or cli/install.php) writes this file for you.
return [
    'env'          => 'production',            // 'development' shows error details — never in production
    'base_url'     => 'https://example.com',   // no trailing slash; include sub-folder if any
    'app_secret'   => 'CHANGE-ME-64-random-hex-characters', // php -r "echo bin2hex(random_bytes(32));"
    'trust_proxy'  => false,                    // true only behind a proxy/CDN that sets X-Forwarded-For/Proto
    'storage_path' => __DIR__ . '/../storage',
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'facematch',
        'user' => 'facematch',
        'pass' => '',
    ],
];
