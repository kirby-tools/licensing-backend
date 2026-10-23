<?php

// Router for PHP's built-in server: answers with the status code the path
// ends in, e.g. `/status/401`, and a body naming a different failure, which
// the client must not read. `/malformed` answers 200 with a body that is
// not JSON.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/malformed') {
    echo 'Service temporarily unavailable';
    return;
}

$status = (int)basename($path);

http_response_code($status);
header('Content-Type: application/json');

echo json_encode($status >= 200 && $status < 300
    ? ['packageName' => 'test/package', 'licenseCompatibility' => '^1.0.0']
    : ['message' => 'Unauthorized']);
