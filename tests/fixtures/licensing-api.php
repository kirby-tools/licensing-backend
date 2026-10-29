<?php

// Router for PHP's built-in server: answers with the status code the path
// ends in, e.g. `/status/404`. Every error body claims `Unauthorized`, so a
// client that reads it instead of the status misreports a 404 or 500.
$status = (int)basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

http_response_code($status);
header('Content-Type: application/json');

echo json_encode($status >= 200 && $status < 300
    ? ['packageName' => 'test/package', 'licenseCompatibility' => '^1.0.0']
    : ['message' => 'Unauthorized']);
